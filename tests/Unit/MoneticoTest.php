<?php

namespace App\Tests\Unit;

use App\Payment\Monetico;
use PHPUnit\Framework\TestCase;

final class MoneticoTest extends TestCase
{
    private const CLE = '0123456789abcdef0123456789abcdef01234567';

    public function testMacSelonLaSpecificationV3(): void
    {
        $monetico = new Monetico('1234567', self::CLE, 'societe', true, false);
        $champs = ['version' => '3.0', 'TPE' => '1234567', 'montant' => '12.00EUR', 'reference' => 'BQ260930ABCD'];

        // Champs triés par clé (ordre ASCII : majuscules d'abord), « cle=valeur » joints par « * ».
        $attendu = strtoupper(hash_hmac('sha1', 'TPE=1234567*montant=12.00EUR*reference=BQ260930ABCD*version=3.0', (string) hex2bin(self::CLE)));
        self::assertSame($attendu, $monetico->mac($champs));
    }

    public function testSignatureDeNotification(): void
    {
        $monetico = new Monetico('1234567', self::CLE, 'societe', true, false);
        $notification = ['TPE' => '1234567', 'date' => '30/09/2026_a_10:00:00', 'montant' => '12.00EUR', 'reference' => 'BQ260930ABCD', 'code-retour' => 'payetest'];
        $notification['MAC'] = strtolower($monetico->mac($notification));

        self::assertTrue($monetico->isSignatureValid($notification), 'MAC insensible à la casse');

        $notification['montant'] = '1.00EUR';
        self::assertFalse($monetico->isSignatureValid($notification), 'montant modifié');
        self::assertFalse($monetico->isSignatureValid(['reference' => 'X']), 'MAC absent');
    }

    public function testAccuse(): void
    {
        $monetico = new Monetico('1', self::CLE, 's', true, false);
        self::assertSame("version=2\ncdr=0\n", $monetico->acknowledgement(true));
        self::assertSame("version=2\ncdr=1\n", $monetico->acknowledgement(false));
    }
}
