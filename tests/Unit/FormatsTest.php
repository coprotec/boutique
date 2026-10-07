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

    public function testTelephone(): void
    {
        foreach (['03 69 28 89 00', '0369288900', '+33 3 69 28 89 00', '06.12.34.56.78'] as $ok) {
            self::assertMatchesRegularExpression(Formats::TELEPHONE, $ok);
        }
        foreach (['12345', '00 69 28 89 00', '+44 20 1234 5678'] as $ko) {
            self::assertDoesNotMatchRegularExpression(Formats::TELEPHONE, $ko);
        }
    }
}
