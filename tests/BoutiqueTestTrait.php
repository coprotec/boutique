<?php

namespace App\Tests;

use App\Catalog\CatalogSynchronizer;
use App\Entity\Session;
use App\Reservation\ContactInput;
use App\Reservation\ReservationRequest;
use App\Reservation\ParticipantInput;
use App\Reservation\CompanyInput;
use App\Entity\Funding;
use App\Smartof\FakeSmartofApi;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Base de test : schéma SQLite neuf, API SmartOF simulée vidée, catalogue synchronisé depuis fixtures/smartof.
 */
trait BoutiqueTestTrait
{
    private function preparerBase(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        static::getContainer()->get(FakeSmartofApi::class)->reset();
        static::getContainer()->get(CatalogSynchronizer::class)->synchronize();
    }

    private function findSession(string $smartofUid): Session
    {
        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Session::class)->findOneBy(['smartofUid' => $smartofUid]);
    }

    private function demande(int $participants): ReservationRequest
    {
        $demande = new ReservationRequest();
        $demande->financement = Funding::Aucun;
        $demande->contact = new ContactInput();
        $demande->contact->prenom = 'Ada';
        $demande->contact->nom = 'Lovelace';
        $demande->contact->email = $demande->contact->emailConfirmation = 'ada@example.com';
        $demande->contact->telephone = '03 69 28 89 00';

        $societe = new CompanyInput();
        $societe->raisonSociale = 'Acme Chauffage';
        $societe->adresse = '1 rue du Test';
        $societe->codePostal = '68000';
        $societe->ville = 'Colmar';
        $societe->siret = '73282932000074';
        $societe->ape = '4322B';
        $societe->nbSalaries = 12;
        $societe->opco = 'Constructys';
        $societe->organisationProfessionnelle = 'CAPEB';
        $societe->dirigeantPrenom = 'Ada';
        $societe->dirigeantNom = 'Lovelace';
        $societe->telephone = '0369288900';
        $societe->email = 'compta@acme.example';
        $demande->societe = $societe;

        for ($i = 0; $i < $participants; ++$i) {
            $p = new ParticipantInput();
            $p->prenom = 'Jean'.$i;
            $p->nom = 'Dupont';
            $p->dateNaissance = '1985-05-12';
            $p->situation = 'Salarié';
            $p->numeroSecu = '185056806612374';
            $demande->addParticipant($p);
        }

        return $demande;
    }
}
