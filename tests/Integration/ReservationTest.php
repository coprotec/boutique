<?php

namespace App\Tests\Integration;

use App\Catalog\Availability;
use App\Entity\Course;
use App\Entity\PaymentMethod;
use App\Entity\OrderStatus;
use App\Reservation\Encryptor;
use App\Reservation\InsufficientSeatsException;
use App\Reservation\ReservationService;
use App\Smartof\FakeSmartofApi;
use App\Smartof\SmartofClient;
use App\Smartof\EnrollmentSender;
use App\Tests\BoutiqueTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReservationTest extends KernelTestCase
{
    use BoutiqueTestTrait;

    private const SESSION_8_PLACES_5_INSCRITS = '5e000000-0000-4000-8000-000000000001';
    private const SESSION_COMPLETE = '5e000000-0000-4000-8000-000000000003';

    protected function setUp(): void
    {
        self::bootKernel();
        $this->preparerBase();
    }

    public function testSynchroNeGardeQueLesFormationsCoprotecActives(): void
    {
        $formations = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Course::class)->findBy(['active' => true]);
        $references = array_map(static fn (Course $f): string => $f->getReference(), $formations);
        sort($references);

        // AUT-01 (autre organisme, filtre boutique) et OLD-01 (archivée) sont exclues.
        self::assertSame(['HAB-B1V', 'PAC-01', 'T68-25'], $references);

        $t68 = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Course::class)->findOneBy(['reference' => 'T68-25']);
        self::assertSame(130000, $t68->getPrixHtCentimes());
        self::assertTrue($t68->isEligibleCpf());
        self::assertSame('t68-25', $t68->getSlug());
    }

    public function testLesPlacesBloqueesParUnPaiementEnCoursNeSontPlusDisponibles(): void
    {
        $session = $this->findSession(self::SESSION_8_PLACES_5_INSCRITS);
        $disponibilite = self::getContainer()->get(Availability::class);
        self::assertSame(3, $disponibilite->remainingSeatsForSession($session));

        $commande = self::getContainer()->get(ReservationService::class)->reserve($session, $this->demande(2), PaymentMethod::Cb);

        self::assertSame(OrderStatus::PaiementEnCours, $commande->getStatut());
        self::assertSame(1, $disponibilite->remainingSeatsForSession($session));

        // Une fois le blocage expiré, les places sont de nouveau disponibles.
        self::assertSame(3, $disponibilite->remainingSeatsForSession($session, $commande->getExpireLe()->modify('+1 second')));
    }

    public function testUneSecondeReservationQuiDepasseLesPlacesEstRefuseeAvantPaiement(): void
    {
        $session = $this->findSession(self::SESSION_8_PLACES_5_INSCRITS);
        $reservateur = self::getContainer()->get(ReservationService::class);

        $reservateur->reserve($session, $this->demande(2), PaymentMethod::Cb);

        try {
            $reservateur->reserve($session, $this->demande(2), PaymentMethod::Virement);
            self::fail('La seconde réservation aurait dû être refusée.');
        } catch (InsufficientSeatsException $e) {
            self::assertSame(1, $e->restantes);
            self::assertStringContainsString('Il ne reste que 1 place', $e->getMessage());
        }
    }

    public function testSessionCompleteRefusee(): void
    {
        $this->expectException(InsufficientSeatsException::class);
        self::getContainer()->get(ReservationService::class)->reserve($this->findSession(self::SESSION_COMPLETE), $this->demande(1), PaymentMethod::Cheque);
    }

    public function testLeNumeroDeSecuEstChiffreEnBase(): void
    {
        $commande = self::getContainer()->get(ReservationService::class)->reserve($this->findSession(self::SESSION_8_PLACES_5_INSCRITS), $this->demande(1), PaymentMethod::Virement);
        $chiffre = $commande->getParticipants()->first()->getNumeroSecuChiffre();

        self::assertStringNotContainsString('185056806612374', $chiffre);
        self::assertSame('185056806612374', self::getContainer()->get(Encryptor::class)->decrypt($chiffre));
    }

    public function testTransmissionSmartofIdempotente(): void
    {
        $commande = self::getContainer()->get(ReservationService::class)->reserve($this->findSession(self::SESSION_8_PLACES_5_INSCRITS), $this->demande(2), PaymentMethod::Virement);
        $transmetteur = self::getContainer()->get(EnrollmentSender::class);
        $fake = self::getContainer()->get(FakeSmartofApi::class);

        self::assertTrue($transmetteur->send($commande));
        self::assertNotNull($commande->getSmartofCommanditaireUid());

        $commanditaires = $fake->created('commanditaires');
        self::assertCount(1, $commanditaires);
        self::assertSame($commande->getNumero(), $commanditaires[0]['customId']);
        self::assertSame('Entreprise', $commanditaires[0]['type']);
        self::assertCount(2, $commanditaires[0]['apprenantUids']);
        self::assertCount(1, $fake->created('entreprises'));

        // Deuxième commande de la même société : l'entreprise SmartOF est retrouvée par SIRET, pas recréée.
        $autre = self::getContainer()->get(ReservationService::class)->reserve($this->findSession('5e000000-0000-4000-8000-000000000002'), $this->demande(1), PaymentMethod::Cheque);
        $transmetteur->send($autre);
        self::assertCount(1, $fake->created('entreprises'));
        self::assertCount(2, $fake->created('commanditaires'));
    }

    public function testRefusDefinitifDeSmartofArreteLesRelances(): void
    {
        // Les 3 dernières places sont prises sur la boutique (virement : validée, pas encore transmise)…
        $commande = self::getContainer()->get(ReservationService::class)->reserve($this->findSession(self::SESSION_8_PLACES_5_INSCRITS), $this->demande(3), PaymentMethod::Virement);

        // … pendant qu'une inscription est saisie directement dans SmartOF sur la même session.
        $smartof = self::getContainer()->get(SmartofClient::class);
        $apprenant = $smartof->request('POST', 'v2/apprenants', ['json' => ['email' => '', 'meta' => ['nom' => 'AUTRE', 'prenom' => 'Canal']]]);
        $smartof->request('POST', 'v2/commanditaires', ['json' => ['type' => 'Particulier', 'sessionUid' => self::SESSION_8_PLACES_5_INSCRITS, 'apprenantUids' => [$apprenant['apprenantUid']]]]);
        $fake = self::getContainer()->get(FakeSmartofApi::class);

        // SmartOF refuse alors l'inscription (simulation : 409, limite atteinte) → surréservation.
        self::assertFalse(self::getContainer()->get(EnrollmentSender::class)->send($commande));
        self::assertSame(1, $commande->getTentativesTransmission());
        self::assertNull($commande->getProchaineTentativeLe(), '409 = erreur définitive, pas de relance automatique');
        self::assertStringContainsString('409', $commande->getDerniereErreur());
        self::assertCount(1, $fake->created('commanditaires'));
    }
}
