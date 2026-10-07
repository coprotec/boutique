<?php

namespace App\Entity;

/**
 * Modes de financement repris de boutique-old. Le CPF n'en fait pas partie : il redirige vers
 * moncompteformation.gouv.fr sans réservation dans la boutique.
 */
enum Funding: string
{
    case Aucun = 'aucun';
    case FranceTravail = 'france_travail';

    public function label(): string
    {
        return match ($this) {
            self::Aucun => 'Sans financement',
            self::FranceTravail => 'France Travail (ex-Pôle emploi)',
        };
    }

    /** @return list<PaymentMethod> */
    public function paymentMethods(): array
    {
        return match ($this) {
            self::Aucun => [PaymentMethod::Cb, PaymentMethod::Virement, PaymentMethod::Cheque],
            self::FranceTravail => [PaymentMethod::Financement],
        };
    }
}
