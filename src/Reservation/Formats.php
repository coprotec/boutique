<?php

namespace App\Reservation;

/**
 * Contrôles de format partagés (formulaire et tests).
 */
final class Formats
{
    /** Téléphone français, national ou +33, séparateurs espace / point / tiret facultatifs. */
    public const TELEPHONE = '/^(?:\+33\s?|0)[1-9](?:[\s.-]?\d{2}){4}$/';

    /**
     * N° de sécurité sociale : format de boutique-old (sexe, année, mois, département dont 2A/2B, 8 chiffres)
     * + contrôle de la clé (97 − numéro mod 97, Corse : 2A → 19, 2B → 18).
     */
    public static function isValidSocialSecurityNumber(string $numero): bool
    {
        if (1 !== preg_match('/^[12]\d{4}(?:\d{2}|2A|2B)\d{8}$/', $numero)) {
            return false;
        }

        $corps = substr($numero, 0, 13);
        $corps = str_replace(['2A', '2B'], ['19', '18'], $corps);
        $cle = 97 - ((int) $corps % 97); // 13 chiffres : tient dans un entier 64 bits

        return $cle === (int) substr($numero, 13, 2);
    }

    /**
     * SIRET : 14 chiffres + clé de Luhn. Exception : les établissements de La Poste (SIREN 356000000) ont une somme
     * de chiffres multiple de 5 (le siège, lui, respecte Luhn).
     */
    public static function isValidSiret(string $siret): bool
    {
        if (1 !== preg_match('/^\d{14}$/', $siret)) {
            return false;
        }

        $somme = 0;
        foreach (str_split(strrev($siret)) as $i => $chiffre) {
            $valeur = (int) $chiffre * (1 === $i % 2 ? 2 : 1);
            $somme += $valeur > 9 ? $valeur - 9 : $valeur;
        }

        return 0 === $somme % 10
            || (str_starts_with($siret, '356000000') && 0 === array_sum(str_split($siret)) % 5);
    }
}
