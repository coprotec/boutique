<?php

namespace App\Reservation;

use App\Entity\Financement;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Données saisies à l'étape 1 (formulaire Vue, envoyé en JSON). Validées côté serveur, qui fait foi.
 * Champs et règles repris de boutique-old (ReservationController::etape1), mieux structurés.
 */
final class DemandeReservation
{
    /** PROVISOIRE (QE-3) : nombre maximum de participants par réservation. */
    public const MAX_PARTICIPANTS = 10;

    /** @var list<ParticipantSaisi> */
    #[Assert\Count(min: 1, max: self::MAX_PARTICIPANTS, minMessage: 'Ajoutez au moins un participant.', maxMessage: 'Au-delà de {{ limit }} participants, contactez le service formation.')]
    #[Assert\Valid]
    public array $participants = [];

    #[Assert\Valid]
    public ContactSaisi $contact;

    #[Assert\Valid]
    public SocieteSaisie $societe;

    #[Assert\NotNull(message: 'Choisissez un mode de financement.')]
    public ?Financement $financement = null;

    #[Assert\Length(max: 12)]
    public string $identifiantFranceTravail = '';

    #[Assert\Length(max: 2000, maxMessage: 'La remarque ne doit pas dépasser {{ limit }} caractères.')]
    public string $remarque = '';

    public function __construct()
    {
        $this->contact = new ContactSaisi();
        $this->societe = new SocieteSaisie();
    }

    #[Assert\Callback]
    public function validerFinancement(ExecutionContextInterface $context): void
    {
        if (Financement::FranceTravail !== $this->financement) {
            return;
        }

        $identifiant = strtoupper(str_replace([' ', '/'], '', $this->identifiantFranceTravail));
        if ('' === $identifiant) {
            $context->buildViolation('L\'identifiant demandeur d\'emploi est obligatoire pour un financement France Travail.')
                ->atPath('identifiantFranceTravail')->addViolation();
        } elseif (1 !== preg_match('/^(?:\d{7}[A-Z]{1,2}|\d{8}[A-Z])$/', $identifiant)) {
            // Format repris de boutique-old : 7 chiffres + 1 ou 2 lettres, ou 8 chiffres + 1 lettre.
            $context->buildViolation('Identifiant France Travail invalide : 7 chiffres suivis d\'une ou deux lettres (ex. 1234567A), ou 8 chiffres et une lettre.')
                ->atPath('identifiantFranceTravail')->addViolation();
        }
    }

    public function identifiantFranceTravailNormalise(): string
    {
        return Financement::FranceTravail === $this->financement
            ? strtoupper(str_replace([' ', '/'], '', $this->identifiantFranceTravail))
            : '';
    }
}
