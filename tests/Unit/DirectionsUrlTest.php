<?php

namespace App\Tests\Unit;

use App\Twig\AppExtension;
use PHPUnit\Framework\TestCase;

final class DirectionsUrlTest extends TestCase
{
    public function testPasDItinerairePourUnLieuNonPhysique(): void
    {
        $extension = new AppExtension();

        self::assertNull($extension->directionsUrl(''));
        self::assertNull($extension->directionsUrl('Classe virtuelle'));
        self::assertNull($extension->directionsUrl('Dans vos locaux (intra-entreprise)'));
    }

    public function testLesLocauxCoprotecPointentVersSonAdresse(): void
    {
        self::assertStringContainsString('68127%20Sainte-Croix-en-Plaine', (string) (new AppExtension())->directionsUrl('Dans les locaux de COPROTEC'));
    }

    public function testUneSalleEstChercheeParSonNom(): void
    {
        self::assertSame('https://www.google.com/maps/search/?api=1&query=Colmar%20CCI', (new AppExtension())->directionsUrl('Colmar CCI'));
    }
}
