<?php

namespace App\Entity;

/**
 * Cycle de vie d'une commande (cf. CAHIER_DES_CHARGES.md §7).
 * La transmission à SmartOF est suivie à part (Order::$transmiseLe), une fois la commande validée.
 */
enum OrderStatus: string
{
    /** Places bloquées pendant le paiement CB, jusqu'à Order::$expireLe. */
    case PaiementEnCours = 'paiement_en_cours';
    /** Paiement CB non abouti dans le délai : places libérées. */
    case Expiree = 'expiree';
    /** Paiement CB refusé par Monetico : places libérées. */
    case PaiementRefuse = 'paiement_refuse';
    /** Payée par CB, ou engagée (virement, chèque, France Travail) : places acquises, à transmettre à SmartOF. */
    case Validee = 'validee';

    public function label(): string
    {
        return match ($this) {
            self::PaiementEnCours => 'Paiement en cours',
            self::Expiree => 'Expirée',
            self::PaiementRefuse => 'Paiement refusé',
            self::Validee => 'Validée',
        };
    }
}
