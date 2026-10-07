<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Smartof\FakeSmartofApi;
use App\Tests\BoutiqueTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CheckoutTest extends WebTestCase
{
    use BoutiqueTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->preparerBase();
    }

    public function testLeCatalogueNAfficheQueLesSessionsReservablesCoprotec(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(5, $crawler->filter('.session'));
        self::assertSelectorTextNotContains('.sessions', 'autre organisme');
        self::assertSelectorTextNotContains('.sessions', 'passée');
        self::assertSelectorTextContains('.sessions', 'Complet');

        $this->client->request('GET', '/formation/aut-01');
        self::assertResponseStatusCodeSame(404);
    }

    public function testErreursDeValidationRenvoyeesChampParChamp(): void
    {
        $csrf = $this->ouvrirFormulaire(2);
        $payload = $this->payload();
        $payload['participants'][0]['numeroSecu'] = '185056806612399';
        $payload['societe']['siret'] = '12345678901234';

        $this->send(2, $payload, $csrf);

        self::assertResponseStatusCodeSame(422);
        $chemins = array_column(json_decode($this->client->getResponse()->getContent(), true)['violations'], 'propertyPath');
        self::assertContains('participants[0].numeroSecu', $chemins);
        self::assertContains('societe.siret', $chemins);
    }

    public function testParcoursCompletPaiementCbSimule(): void
    {
        $csrf = $this->ouvrirFormulaire(2);
        $this->send(2, $this->payload(2), $csrf);
        self::assertResponseIsSuccessful();

        $crawler = $this->client->request('GET', '/reservation/2/recapitulatif');
        // Espaces insécables (fines) du format monétaire français.
        self::assertMatchesRegularExpression('/Total TTC\s*3\h120\h€/u', $crawler->filter('.resume')->text());
        $form = $crawler->filter('form.tunnel')->form(['mode' => 'cb', 'consentement' => '1']);
        $this->client->submit($form);

        // Redirection vers la page qui poste le formulaire signé (ici vers le simulateur Monetico).
        $crawler = $this->client->followRedirect();
        $champs = $crawler->filter('form#paiement')->form()->getValues();
        self::assertSame('3120.00EUR', $champs['montant']);
        self::assertSame(12, \strlen($champs['reference']));

        $this->client->request('POST', $crawler->filter('form#paiement')->attr('action'), $champs + ['resultat' => 'accepte']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.panneau', 'Réservation confirmée');

        $commande = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Order::class)->findOneBy(['numero' => $champs['reference']]);
        self::assertSame(OrderStatus::Validee, $commande->getStatut());
        self::assertNotNull($commande->getTransmiseLe(), 'inscrite dans SmartOF dès la notification de paiement');
        self::assertCount(1, self::getContainer()->get(FakeSmartofApi::class)->created('commanditaires'));
    }

    public function testNotificationMoneticoFalsifieeRejetee(): void
    {
        $this->client->request('POST', '/paiement/monetico/notification', ['reference' => 'BQ000000XXXX', 'code-retour' => 'paiement', 'MAC' => 'FAUX']);

        self::assertResponseIsSuccessful();
        self::assertSame("version=2\ncdr=1\n", $this->client->getResponse()->getContent());
    }

    private function ouvrirFormulaire(int $session): string
    {
        $crawler = $this->client->request('GET', "/reservation/$session");
        self::assertResponseIsSuccessful();

        return json_decode($crawler->filter('[data-vue="reservation-form"]')->attr('data-props'), true)['config']['csrf'];
    }

    /** @param array<string, mixed> $payload */
    private function send(int $session, array $payload, string $csrf): void
    {
        $this->client->request('POST', "/reservation/$session", server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], content: json_encode($payload));
    }

    /** @return array<string, mixed> */
    private function payload(int $participants = 1): array
    {
        return [
            'participants' => array_fill(0, $participants, ['prenom' => 'Jean', 'nom' => 'Dupont', 'dateNaissance' => '1985-05-12', 'situation' => 'Salarié', 'numeroSecu' => '1 85 05 68 066 123 74']),
            'contact' => ['prenom' => 'Ada', 'nom' => 'Lovelace', 'email' => 'ada@example.com', 'emailConfirmation' => 'ada@example.com', 'telephone' => '03 69 28 89 00'],
            'societe' => ['raisonSociale' => 'Acme', 'adresse' => '1 rue du Test', 'codePostal' => '68000', 'ville' => 'Colmar', 'siret' => '73282932000074', 'ape' => '4322B', 'tvaIntracom' => '', 'nbSalaries' => 12, 'opco' => 'Constructys', 'organisationProfessionnelle' => 'CAPEB', 'dirigeantPrenom' => 'Ada', 'dirigeantNom' => 'Lovelace', 'telephone' => '0369288900', 'email' => 'compta@acme.example'],
            'financement' => 'aucun',
            'identifiantFranceTravail' => '',
            'remarque' => '',
        ];
    }
}
