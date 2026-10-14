<?php

namespace App\Entity;

enum PaymentMethod: string
{
    case Cb = 'cb';
    case Virement = 'virement';
    /** Plus proposé (QE-8, QE-21) ; conservé pour lire les commandes déjà enregistrées. */
    case Cheque = 'cheque';
    /** Prise en charge par France Travail : rien à payer en ligne. */
    case Financement = 'financement';

    public function label(): string
    {
        return match ($this) {
            self::Cb => 'Carte bancaire',
            self::Virement => 'Virement bancaire',
            self::Cheque => 'Chèque',
            self::Financement => 'Prise en charge France Travail',
        };
    }

    public function isOnline(): bool
    {
        return self::Cb === $this;
    }
}
