<?php

namespace App\Reservation;

final class InsufficientSeatsException extends \RuntimeException
{
    public function __construct(public readonly int $restantes)
    {
        parent::__construct(0 === $restantes
            ? 'Cette session est désormais complète ou fermée.'
            : \sprintf('Il ne reste que %d place%s sur cette session : réduisez le nombre de participants ou choisissez une autre date.', $restantes, $restantes > 1 ? 's' : ''));
    }
}
