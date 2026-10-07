<?php

namespace App\Smartof;

use App\Entity\Order;
use App\Entity\Participant;
use App\Notification\Notifier;
use App\Reservation\Encryptor;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;

/**
 * Inscription directe d'une commande validée dans SmartOF (CAHIER_DES_CHARGES.md §6.3) :
 * entreprise (par SIRET) → apprenants → commanditaire Entreprise avec customId = n° de commande.
 *
 * Idempotent : si un commanditaire porte déjà ce n° de commande, la commande est marquée transmise sans rien recréer.
 * Échec temporaire → nouvelle tentative plus tard (cron) ; échec définitif (400/403/404/409) → alerte, intervention manuelle.
 */
class EnrollmentSender
{
    /** Délais entre deux tentatives après une erreur temporaire, en minutes. */
    private const RELANCES = [5, 15, 60, 180, 720];

    public function __construct(
        private readonly SmartofClient $smartof,
        private readonly EntityManagerInterface $em,
        private readonly Encryptor $chiffreur,
        private readonly Notifier $notificateur,
        private readonly LoggerInterface $logger,
        // PROVISOIRE (QE-23) : correspondance donnée boutique → champ personnalisé SmartOF, ex.
        // « numeroSecu:custom_field_1,situation:custom_field_2 ». Donnée absente de la liste = non transmise.
        #[Autowire('%env(SMARTOF_CHAMPS_APPRENANT)%')] private readonly string $champsApprenant,
        #[Autowire('%env(SMARTOF_CHAMPS_COMMANDITAIRE)%')] private readonly string $champsCommanditaire,
    ) {
    }

    public function send(Order $commande): bool
    {
        if (null !== $commande->getTransmiseLe()) {
            return true;
        }
        $maintenant = new \DateTimeImmutable();

        try {
            $uid = $this->existingSponsor($commande) ?? $this->createEnrollment($commande);
            $commande->markSent($uid, $maintenant);
            $this->logger->info('Commande transmise à SmartOF', ['commande' => $commande->getNumero(), 'commanditaire' => $uid]);
            $this->em->flush();

            return true;
        } catch (SmartofException $e) {
            $this->fail($commande, $e->getMessage(), !$e->isDefinitive(), $maintenant, 409 === $e->status);
        } catch (HttpException $e) {
            $this->fail($commande, 'SmartOF injoignable : '.$e->getMessage(), true, $maintenant, false);
        }
        $this->em->flush();

        return false;
    }

    private function existingSponsor(Order $commande): ?string
    {
        foreach ($this->smartof->paginate('v2/commanditaires', ['customId' => ['eq' => $commande->getNumero()]]) as $commanditaire) {
            return $commanditaire['commanditaireUid'];
        }

        return null;
    }

    private function createEnrollment(Order $commande): string
    {
        $entrepriseUid = $this->company($commande);

        $apprenantUids = [];
        foreach ($commande->getParticipants() as $participant) {
            $apprenantUids[] = $this->learner($participant, $entrepriseUid);
        }

        $payload = [
            'type' => 'Entreprise',
            'sessionUid' => $commande->getSession()->getSmartofUid(),
            'entrepriseUid' => $entrepriseUid,
            'apprenantUids' => $apprenantUids,
            'ajoutCreneaux' => true,
            'customId' => $commande->getNumero(),
            // Pas de « budget » : SmartOF applique le tarif unique du produit (QE-10 ; PROVISOIRE QSO-4).
        ];
        $customFields = $this->customFields($this->champsCommanditaire, [
            'numeroCommande' => $commande->getNumero(),
            'modePaiement' => $commande->getModePaiement()->label(),
            'financement' => $commande->getFinancement()->label(),
            'identifiantFranceTravail' => $commande->getIdentifiantFranceTravail(),
            'referencePaiement' => (string) ($commande->getPaiementDetails()['numauto'] ?? ''),
            'opco' => $commande->getSociete()->opco,
            'organisationProfessionnelle' => $commande->getSociete()->organisationProfessionnelle,
            'remarque' => $commande->getRemarque(),
        ]);
        if ([] !== $customFields) {
            $payload['custom_fields'] = $customFields;
        }

        $reponse = $this->smartof->request('POST', 'v2/commanditaires', ['json' => $payload]);

        return $reponse['commanditaireUids'][0] ?? throw new SmartofException('SmartOF n\'a pas renvoyé d\'UID de commanditaire.', 500, $reponse);
    }

    private function company(Order $commande): string
    {
        $societe = $commande->getSociete();
        foreach ($this->smartof->paginate('v2/entreprises', ['meta.siret' => ['eq' => $societe->siret]]) as $entreprise) {
            return $entreprise['entrepriseUid'];
        }

        $adresse = ['rue' => $societe->adresse, 'complementAdresse' => '', 'codePostal' => $societe->codePostal, 'ville' => $societe->ville];
        $reponse = $this->smartof->request('POST', 'v2/entreprises', ['json' => [
            'customId' => '',
            'contactClientUids' => [],
            'defaultContactClientUids' => [],
            'archived' => false,
            'meta' => [
                'nom' => $societe->raisonSociale,
                'siret' => $societe->siret,
                'email' => $societe->email,
                'tel' => $this->phone($societe->telephone),
                'numeroCompteComptable' => '',
                'adresse' => $adresse,
                'provenanceBPF' => '', // PROVISOIRE (QE-24)
            ],
            'facturation' => ['nomClient' => $societe->raisonSociale, 'adresse' => $adresse],
        ]]);

        return $reponse['entrepriseUid'];
    }

    /** Apprenant existant retrouvé par nom + prénom + date de naissance (PROVISOIRE, QSO-12), sinon créé. */
    private function learner(Participant $participant, string $entrepriseUid): string
    {
        if (null !== $participant->getSmartofApprenantUid()) {
            return $participant->getSmartofApprenantUid();
        }

        $naissance = $participant->getDateNaissance()->format('Y-m-d');
        $uid = null;
        foreach ($this->smartof->paginate('v2/apprenants', [
            'meta.nom' => ['eq' => $participant->getNom()],
            'meta.prenom' => ['eq' => $participant->getPrenom()],
            'meta.dateNaissance' => ['eq' => $naissance],
        ]) as $apprenant) {
            $uid = $apprenant['apprenantUid'];
            break;
        }

        if (null === $uid) {
            $payload = [
                'customId' => '',
                'email' => '',
                'entrepriseUids' => [$entrepriseUid],
                'archived' => false,
                'meta' => [
                    'nom' => $participant->getNom(),
                    'nomUsage' => '',
                    'prenom' => $participant->getPrenom(),
                    'fonction' => '',
                    'lieuActivite' => '',
                    'dateNaissance' => $naissance,
                    'tel' => '',
                    'adresse' => ['rue' => '', 'complementAdresse' => '', 'codePostal' => '', 'ville' => ''],
                    'civilite' => 'Non renseigné',
                    'numeroCompteComptable' => '',
                    'statutBPF' => '', // PROVISOIRE (QE-24)
                ],
            ];
            $customFields = $this->customFields($this->champsApprenant, [
                'numeroSecu' => '' !== $participant->getNumeroSecuChiffre() ? $this->chiffreur->decrypt($participant->getNumeroSecuChiffre()) : '',
                'situation' => $participant->getSituation(),
            ]);
            if ([] !== $customFields) {
                $payload['custom_fields'] = $customFields;
            }
            $uid = $this->smartof->request('POST', 'v2/apprenants', ['json' => $payload])['apprenantUid'];
        }

        // Mémorisé tout de suite : une relance ne recrée pas l'apprenant.
        $participant->setSmartofApprenantUid($uid);
        $this->em->flush();

        return $uid;
    }

    /**
     * @param array<string, string> $valeurs
     *
     * @return array<string, string>
     */
    private function customFields(string $correspondance, array $valeurs): array
    {
        $champs = [];
        foreach (array_filter(array_map('trim', explode(',', $correspondance))) as $paire) {
            [$donnee, $champ] = array_map('trim', explode(':', $paire, 2)) + [1 => ''];
            if (isset($valeurs[$donnee]) && 1 === preg_match('/^custom_field_(?:[1-9]|1\d|20)$/', $champ)) {
                $champs[$champ] = $valeurs[$donnee];
            }
        }

        return $champs;
    }

    /** Format « international simple » attendu par SmartOF : +33XXXXXXXXX. */
    private function phone(string $telephone): string
    {
        $chiffres = preg_replace('/\D/', '', $telephone) ?? '';

        return match (true) {
            '' === $chiffres => '',
            str_starts_with($chiffres, '33') => '+'.$chiffres,
            default => '+33'.substr($chiffres, 1),
        };
    }

    private function fail(Order $commande, string $erreur, bool $temporaire, \DateTimeImmutable $maintenant, bool $surreservation): void
    {
        $tentative = $commande->getTentativesTransmission();
        $prochaine = $temporaire && isset(self::RELANCES[$tentative])
            ? $maintenant->modify(\sprintf('+%d minutes', self::RELANCES[$tentative]))
            : null;
        $commande->markSendFailed($erreur, $prochaine);

        $this->logger->error('Échec de transmission à SmartOF', ['commande' => $commande->getNumero(), 'erreur' => $erreur, 'prochaineTentative' => $prochaine?->format(\DATE_ATOM)]);

        // Alerte dès qu'une intervention humaine est nécessaire : erreur définitive ou relances épuisées.
        if (null === $prochaine) {
            $this->notificateur->sendFailureAlert($commande, $surreservation);
        }
    }
}
