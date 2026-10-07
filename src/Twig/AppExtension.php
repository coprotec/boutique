<?php

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * Formats français (prix, dates) sans dépendre de twig/intl-extra.
 */
final class AppExtension
{
    /** 130000 → « 1 300 € » ; 45050 → « 450,50 € ». */
    #[AsTwigFilter('price')]
    public function price(?int $centimes): string
    {
        if (null === $centimes) {
            return '';
        }
        $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0 === $centimes % 100 ? 0 : 2);

        return (string) $formatter->formatCurrency($centimes / 100, 'EUR');
    }

    /**
     * Lien d'itinéraire Google Maps pour un lieu de session, ou null s'il n'y a pas de lieu physique
     * (classe virtuelle, intra-entreprise). Un simple lien plutôt qu'une carte intégrée : pas de cookie
     * tiers ni de bandeau de consentement, et il ouvre l'application de navigation sur mobile.
     * PROVISOIRE (QSO-16) : on cherche par le nom de la salle SmartOF, faute d'adresse lue par l'API.
     */
    #[AsTwigFilter('directions_url')]
    public function directionsUrl(string $lieu): ?string
    {
        $adresse = match ($lieu) {
            '', 'Classe virtuelle', 'Dans vos locaux (intra-entreprise)' => null,
            'Dans les locaux de COPROTEC' => 'COPROTEC, 12 impasse Montgolfier, 68127 Sainte-Croix-en-Plaine',
            default => $lieu,
        };

        return null === $adresse ? null : 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($adresse);
    }

    /** Motifs ICU : « d MMM », « EEEE d MMMM y », « MMMM y »… */
    #[AsTwigFilter('date_fr')]
    public function frenchDate(\DateTimeInterface $date, string $motif = 'd MMMM y'): string
    {
        $formatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Europe/Paris', null, $motif);

        return (string) $formatter->format($date);
    }
}
