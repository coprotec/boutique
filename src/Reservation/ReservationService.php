<?php

namespace App\Reservation;

use App\Catalog\Availability;
use App\Entity\Order;
use App\Entity\PaymentMethod;
use App\Entity\Participant;
use App\Entity\Session;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Crée la commande en bloquant les places, sous verrou sur la session (cf. CAHIER_DES_CHARGES.md §5.4) :
 * deux réservations simultanées ne peuvent pas prendre les mêmes places.
 */
class ReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Availability $disponibilite,
        private readonly Encryptor $chiffreur,
        // Durée du blocage des places pendant le paiement CB (décision technique, ex-QE-7).
        #[Autowire('%env(int:BOUTIQUE_BLOCAGE_PAIEMENT_MINUTES)%')] private readonly int $blocageMinutes,
    ) {
    }

    /**
     * @throws InsufficientSeatsException
     */
    public function reserve(Session $session, ReservationRequest $demande, PaymentMethod $mode): Order
    {
        if (!\in_array($mode, $demande->financement->paymentMethods(), true)) {
            throw new \InvalidArgumentException('Mode de paiement incompatible avec le financement choisi.');
        }

        // Lecture en direct du remplissage SmartOF, hors verrou (appel réseau).
        $this->disponibilite->refresh($session);

        return $this->em->wrapInTransaction(function () use ($session, $demande, $mode): Order {
            $this->em->lock($session, LockMode::PESSIMISTIC_WRITE);
            $maintenant = new \DateTimeImmutable();

            $restantes = $this->disponibilite->remainingSeatsForSession($session, $maintenant);
            $demandees = \count($demande->getParticipants());
            if (!$session->isOuverte() || (null !== $restantes && $restantes < $demandees)) {
                throw new InsufficientSeatsException($session->isOuverte() ? (int) $restantes : 0);
            }

            $commande = new Order($this->generateNumber($maintenant), bin2hex(random_bytes(16)), $session, $demande->financement, $mode);
            $this->hydrate($commande, $demande);

            if ($mode->isOnline()) {
                $commande->setExpireLe($maintenant->modify(\sprintf('+%d minutes', $this->blocageMinutes)));
            } else {
                // PROVISOIRE (QE-8, QE-14) : virement, chèque et France Travail engagent les places tout de suite ;
                // l'inscription part dans SmartOF avec le mode de paiement, la réception est suivie par la compta.
                $commande->validate($maintenant);
            }

            $this->em->persist($commande);

            return $commande;
        });
    }

    private function hydrate(Order $commande, ReservationRequest $demande): void
    {
        $contact = $commande->getContact();
        $contact->prenom = trim($demande->contact->prenom);
        $contact->nom = trim($demande->contact->nom);
        $contact->email = mb_strtolower(trim($demande->contact->email));
        $contact->telephone = trim($demande->contact->telephone);

        $saisie = $demande->societe;
        $societe = $commande->getSociete();
        $societe->raisonSociale = trim($saisie->raisonSociale);
        $societe->adresse = trim($saisie->adresse);
        $societe->codePostal = $saisie->codePostal;
        $societe->ville = trim($saisie->ville);
        $societe->siret = $saisie->normalizedSiret();
        $societe->ape = $saisie->normalizedApe();
        $societe->tvaIntracom = strtoupper(trim($saisie->tvaIntracom));
        $societe->nbSalaries = (int) $saisie->nbSalaries;
        $societe->opco = trim($saisie->opco);
        $societe->organisationProfessionnelle = $saisie->organisationProfessionnelle;
        $societe->dirigeantPrenom = trim($saisie->dirigeantPrenom);
        $societe->dirigeantNom = trim($saisie->dirigeantNom);
        $societe->telephone = trim($saisie->telephone);
        $societe->email = mb_strtolower(trim($saisie->email));

        $commande->setIdentifiantFranceTravail($demande->normalizedFranceTravailId());
        $commande->setRemarque(trim($demande->remarque));

        foreach ($demande->getParticipants() as $saisi) {
            $commande->addParticipant(new Participant(
                trim($saisi->prenom),
                mb_strtoupper(trim($saisi->nom)),
                new \DateTimeImmutable($saisi->dateNaissance),
                $saisi->situation,
                $this->chiffreur->encrypt($saisi->normalizedSocialSecurityNumber()),
            ));
        }
    }

    /**
     * Référence de commande, aussi utilisée par Monetico (12 caractères alphanumériques max) :
     * « BQ » + AAMMJJ + 4 caractères aléatoires, ex. BQ260929K7X2.
     */
    private function generateNumber(\DateTimeImmutable $maintenant): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $suffixe = '';
        for ($i = 0; $i < 4; ++$i) {
            $suffixe .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
        }

        return 'BQ'.$maintenant->format('ymd').$suffixe;
    }
}
