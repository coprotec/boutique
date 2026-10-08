<?php

namespace App\Tests\Unit;

use App\Reservation\Formats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormatsTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function numerosSecu(): iterable
    {
        yield 'valide' => ['185056806612374', true];
        yield 'mauvaise clé' => ['185056806612399', false];
        yield 'trop court' => ['18505680661237', false];
        yield 'sexe invalide' => ['385056806612374', false];
        // Corse : la clé se calcule en remplaçant 2A par 19 (et 2B par 18).
        yield 'Corse 2A' => ['185052A123456'.\sprintf('%02d', 97 - (1850519123456 % 97)), true];
        yield 'Corse 2A mauvaise clé' => ['185052A12345600', false];
    }

    #[DataProvider('numerosSecu')]
    public function testNumeroSecu(string $numero, bool $attendu): void
    {
        self::assertSame($attendu, Formats::isValidSocialSecurityNumber($numero));
    }

    public function testSiret(): void
    {
        self::assertTrue(Formats::isValidSiret('73282932000074'));
        self::assertFalse(Formats::isValidSiret('73282932000075'));
        self::assertFalse(Formats::isValidSiret('7328293200007'));
        // La Poste : le siège respecte Luhn, ses établissements ont une somme de chiffres multiple de 5.
        self::assertTrue(Formats::isValidSiret('35600000000048'));
        self::assertTrue(Formats::isValidSiret('35600000000001'));
        self::assertFalse(Formats::isValidSiret('35600000000002'));
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function telephones(): iterable
    {
        yield 'national' => ['03 69 28 89 00', '+33369288900'];
        yield 'points' => ['06.12.34.56.78', '+33612345678'];
        yield '+33' => ['+33 3 69 28 89 00', '+33369288900'];
        yield '+33 (0)' => ['+33 (0)6 12 34 56 78', '+33612345678'];
        yield '+33 0' => ['+33 06 12 34 56 78', '+33612345678'];
        yield 'Suisse' => ['+41 76 333 01 11', '+41763330111'];
        yield 'Suisse en 00' => ['0041 76 333 01 11', '+41763330111'];
        yield 'Royaume-Uni' => ['+44 20 1234 5678', '+442012345678'];
        yield 'trop court' => ['12345', null];
        yield 'national à 9 chiffres' => ['03 69 28 89 0', null];
        yield 'indicatif commençant par 0' => ['+0 69 28 89 00', null];
        yield 'étranger sans indicatif' => ['76 333 01 11', null];
        yield '+33 trop long' => ['+33 3 69 28 89 00 1', null];
        yield 'lettres' => ['03 69 AB 89 00', null];
    }

    #[DataProvider('telephones')]
    public function testNormalisationTelephone(string $saisie, ?string $attendu): void
    {
        self::assertSame($attendu, Formats::normalizePhone($saisie));
    }

    public function testDecoupageEtAffichageTelephone(): void
    {
        self::assertSame(['33', '369288900'], Formats::splitPhone('+33369288900'));
        self::assertSame(['41', '763330111'], Formats::splitPhone('+41763330111'));
        self::assertSame(['1', '4155550123'], Formats::splitPhone('+14155550123'));
        self::assertSame(['352', '621123456'], Formats::splitPhone('+352621123456'));
        self::assertSame(['33', '369288900'], Formats::splitPhone('03 69 28 89 00')); // saisie d'avant E.164
        self::assertSame('03 69 28 89 00', Formats::formatPhone('+33369288900'));
        self::assertSame('+41 763330111', Formats::formatPhone('+41763330111'));
    }

    public function testCodePostalSelonLePays(): void
    {
        self::assertNull(Formats::postalCodeError('68000', 'FR'));
        self::assertNull(Formats::postalCodeError('97400', 'FR'));
        self::assertSame('Code postal invalide : 5 chiffres.', Formats::postalCodeError('1201', 'FR'));
        self::assertNull(Formats::postalCodeError('1201', 'CH'));
        self::assertSame('Code postal invalide : 4 chiffres.', Formats::postalCodeError('68000', 'CH'));
        self::assertNull(Formats::postalCodeError('98000', 'MC'));
        self::assertNull(Formats::postalCodeError('SW1A 1AA', 'GB'));
        self::assertSame('Code postal invalide.', Formats::postalCodeError('#', 'GB'));
    }
}
