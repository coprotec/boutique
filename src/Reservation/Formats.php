<?php

namespace App\Reservation;

/**
 * Contrôles de format partagés (formulaire et tests).
 */
final class Formats
{
    public const PAYS_DEFAUT = 'FR';

    public const PHONE_ERROR = 'Numéro invalide. Hors de France, commencez par l\'indicatif du pays (ex. +41 76 333 01 11).';

    /**
     * Codes postaux contrôlés par pays : [motif, format attendu]. Les autres pays n'ont qu'un contrôle générique
     * (2 à 10 lettres, chiffres, espaces ou tirets), faute de pouvoir tous les maintenir.
     */
    private const CODES_POSTAUX = [
        'FR' => ['/^\d{5}$/', '5 chiffres'],
        'MC' => ['/^980\d{2}$/', '980 suivi de 2 chiffres'],
        'BE' => ['/^\d{4}$/', '4 chiffres'],
        'CH' => ['/^\d{4}$/', '4 chiffres'],
        'LU' => ['/^\d{4}$/', '4 chiffres'],
        'DE' => ['/^\d{5}$/', '5 chiffres'],
        'ES' => ['/^\d{5}$/', '5 chiffres'],
        'IT' => ['/^\d{5}$/', '5 chiffres'],
    ];

    /** Indicatifs pays à 1 et 2 chiffres (UIT, sans ambiguïté de préfixe) : tous les autres en ont 3. */
    private const INDICATIFS_COURTS = ['1', '7', '20', '27', '30', '31', '32', '33', '34', '36', '39', '40', '41', '43', '44', '45', '46', '47', '48', '49',
        '51', '52', '53', '54', '55', '56', '57', '58', '60', '61', '62', '63', '64', '65', '66', '81', '82', '84', '86', '90', '91', '92', '93', '94', '95', '98'];

    /**
     * Téléphone saisi → format international E.164 (+33369288900), ou null s'il est invalide.
     * France : 0X XX XX XX XX ou +33 (contrôle strict). Ailleurs : indicatif obligatoire (+41…, ou 0041…), puis
     * 7 à 15 chiffres au total ; on ne vérifie pas le plan de numérotation du pays.
     */
    public static function normalizePhone(string $saisie): ?string
    {
        $numero = preg_replace('/[\s.\-\/()]/', '', str_replace('(0)', '', trim($saisie))) ?? '';
        if (str_starts_with($numero, '00')) {
            $numero = '+'.substr($numero, 2);
        }
        if (1 === preg_match('/^0([1-9]\d{8})$/', $numero, $m)) {
            return '+33'.$m[1];
        }
        if (str_starts_with($numero, '+33')) {
            $numero = preg_replace('/^\+330/', '+33', $numero) ?? $numero; // « +33 0 6 … »

            return 1 === preg_match('/^\+33[1-9]\d{8}$/', $numero) ? $numero : null;
        }

        return 1 === preg_match('/^\+[1-9]\d{6,14}$/', $numero) ? $numero : null;
    }

    /**
     * Sépare l'indicatif du numéro national : +41763330111 → ['41', '763330111']. Un numéro non normalisé (saisie
     * antérieure au format E.164) est d'abord normalisé ; s'il reste invalide, il est considéré comme français.
     *
     * @return array{string, string}
     */
    public static function splitPhone(string $telephone): array
    {
        $numero = self::normalizePhone($telephone) ?? '+33'.substr(preg_replace('/\D/', '', $telephone) ?? '', 1);
        $chiffres = substr($numero, 1);
        foreach ([1, 2] as $longueur) {
            if (\in_array(substr($chiffres, 0, $longueur), self::INDICATIFS_COURTS, true)) {
                return [substr($chiffres, 0, $longueur), substr($chiffres, $longueur)];
            }
        }

        return [substr($chiffres, 0, 3), substr($chiffres, 3)];
    }

    /** Affichage : format national pour la France (03 69 28 89 00), international ailleurs (+41 763330111). */
    public static function formatPhone(string $telephone): string
    {
        [$indicatif, $national] = self::splitPhone($telephone);
        if ('' === $national) {
            return $telephone;
        }

        return '33' === $indicatif ? trim(chunk_split('0'.$national, 2, ' ')) : "+$indicatif $national";
    }

    /** Message d'erreur si le code postal ne convient pas au pays, sinon null. */
    public static function postalCodeError(string $codePostal, string $pays): ?string
    {
        [$motif, $format] = self::CODES_POSTAUX[$pays] ?? ['/^[A-Z0-9][A-Z0-9 -]{1,9}$/i', null];
        if (1 === preg_match($motif, $codePostal)) {
            return null;
        }

        return null === $format ? 'Code postal invalide.' : "Code postal invalide : $format.";
    }

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
