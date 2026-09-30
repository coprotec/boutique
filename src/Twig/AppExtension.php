<?php

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * Formats français (prix, dates) sans dépendre de twig/intl-extra.
 */
final class AppExtension
{
    /** 130000 → « 1 300 € » ; 45050 → « 450,50 € ». */
    #[AsTwigFilter('prix')]
    public function prix(?int $centimes): string
    {
        if (null === $centimes) {
            return '';
        }
        $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0 === $centimes % 100 ? 0 : 2);

        return (string) $formatter->formatCurrency($centimes / 100, 'EUR');
    }

    /** Motifs ICU : « d MMM », « EEEE d MMMM y », « MMMM y »… */
    #[AsTwigFilter('date_fr')]
    public function dateFr(\DateTimeInterface $date, string $motif = 'd MMMM y'): string
    {
        $formatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Europe/Paris', null, $motif);

        return (string) $formatter->format($date);
    }
}
