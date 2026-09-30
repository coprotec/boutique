<?php

namespace App\Reservation;

use App\Catalogue\Disponibilite;
use App\Entity\Commande;
use App\Entity\ModePaiement;
use App\Entity\Participant;
use App\Entity\Session;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Crée la commande en bloquant les places, sous verrou sur la session (cf. CAHIER_DES_CHARGES.md §5.4) :
 * deux réservations simultanées ne peuvent pas prendre les mêmes places.
 */
class Reservateur
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Disponibilite $disponibilite,
        private readonly Chiffreur $chiffreur,
        // PROVISOIRE (QE-7) : durée du blocage des places pendant le paiement CB.
        #[Autowire('%env(int:BOUTIQUE_BLOCAGE_PAIEMENT_MINUTES)%')] private readonly int $blocageMinutes,
    ) {
    }

    /**
     * @throws PlacesInsuffisantes
     */
    public function reserver(Session $session, DemandeReservation $demande, ModePaiement $mode): Commande
    {
        if (!\in_array($mode, $demande->financement->modesPaiement(), true)) {
            throw new \InvalidArgumentException('Mode de paiement incompatible avec le financement choisi.');
        }

        // Lecture en direct du remplissage SmartOF, hors verrou (appel réseau).
        $this->disponibilite->rafraichir($session);

        return $this->em->wrapInTransaction(function () use ($session, $demande, $mode): Commande {
            $this->em->lock($session, LockMode::PESSIMISTIC_WRITE);
            $maintenant = new \DateTimeImmutable();

            $restantes = $this->disponibilite->placesRestantesSession($session, $maintenant);
            $demandees = \count($demande->participants);
            if (!$session->isOuverte() || (null !== $restantes && $restantes < $demandees)) {
                throw new PlacesInsuffisantes($session->isOuverte() ? (int) $restantes : 0);
            }

            $commande = new Commande($this->numero($maintenant), bin2hex(random_bytes(16)), $session, $demande->financement, $mode);
            $this->hydrater($commande, $demande);

            if ($mode->enLigne()) {
                $commande->setExpireLe($maintenant->modify(\sprintf('+%d minutes', $this->blocageMinutes)));
            } else {
                // PROVISOIRE (QE-8, QE-14) : virement, chèque et France Travail engagent les places tout de suite ;
                // l'inscription part dans SmartOF avec le mode de paiement, la réception est suivie par la compta.
                $commande->valider($maintenant);
            }

            $this->em->persist($commande);

            return $commande;
        });
    }

    private function hydrater(Commande $commande, DemandeReservation $demande): void
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
        $societe->siret = $saisie->siretNormalise();
        $societe->ape = $saisie->apeNormalise();
        $societe->tvaIntracom = strtoupper(trim($saisie->tvaIntracom));
        $societe->nbSalaries = (int) $saisie->nbSalaries;
        $societe->opco = trim($saisie->opco);
        $societe->organisationProfessionnelle = $saisie->organisationProfessionnelle;
        $societe->dirigeantPrenom = trim($saisie->dirigeantPrenom);
        $societe->dirigeantNom = trim($saisie->dirigeantNom);
        $societe->telephone = trim($saisie->telephone);
        $societe->email = mb_strtolower(trim($saisie->email));

        $commande->setIdentifiantFranceTravail($demande->identifiantFranceTravailNormalise());
        $commande->setRemarque(trim($demande->remarque));

        foreach ($demande->participants as $saisi) {
            $commande->ajouterParticipant(new Participant(
                trim($saisi->prenom),
                mb_strtoupper(trim($saisi->nom)),
                new \DateTimeImmutable($saisi->dateNaissance),
                $saisi->situation,
                $this->chiffreur->chiffrer($saisi->numeroSecuNormalise()),
            ));
        }
    }

    /**
     * Référence de commande, aussi utilisée par Monetico (12 caractères alphanumériques max) :
     * « BQ » + AAMMJJ + 4 caractères aléatoires, ex. BQ260929K7X2.
     */
    private function numero(\DateTimeImmutable $maintenant): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $suffixe = '';
        for ($i = 0; $i < 4; ++$i) {
            $suffixe .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
        }

        return 'BQ'.$maintenant->format('ymd').$suffixe;
    }
}
