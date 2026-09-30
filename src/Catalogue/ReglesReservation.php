<?php

namespace App\Catalogue;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Règles de calendrier de la boutique.
 */
class ReglesReservation
{
    public function __construct(
        // PROVISOIRE (QE-6) : une session est réservable jusqu'à N jours avant son début (1 = jusqu'à la veille).
        #[Autowire('%env(int:BOUTIQUE_JOURS_AVANT_SESSION)%')] private readonly int $joursAvantSession,
    ) {
    }

    /** Date de début minimale d'une session pour être encore réservable. */
    public function debutMin(?\DateTimeImmutable $maintenant = null): \DateTimeImmutable
    {
        $maintenant ??= new \DateTimeImmutable();

        return $maintenant->setTime(0, 0)->modify(\sprintf('+%d days', $this->joursAvantSession));
    }
}
