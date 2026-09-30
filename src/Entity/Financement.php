<?php

namespace App\Entity;

/**
 * Modes de financement repris de boutique-old. Le CPF n'en fait pas partie : il redirige vers
 * moncompteformation.gouv.fr sans réservation dans la boutique.
 */
enum Financement: string
{
    case Aucun = 'aucun';
    case FranceTravail = 'france_travail';

    public function libelle(): string
    {
        return match ($this) {
            self::Aucun => 'Sans financement',
            self::FranceTravail => 'France Travail (ex-Pôle emploi)',
        };
    }

    /** @return list<ModePaiement> */
    public function modesPaiement(): array
    {
        return match ($this) {
            self::Aucun => [ModePaiement::Cb, ModePaiement::Virement, ModePaiement::Cheque],
            self::FranceTravail => [ModePaiement::Financement],
        };
    }
}
