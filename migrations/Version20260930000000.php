<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial (MySQL 8) : catalogue synchronisé depuis SmartOF et commandes de la boutique.
 * SQL issu de `doctrine:schema:create --dump-sql` (serverVersion 8.0.32).
 */
final class Version20260930000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial : formation, session_formation, commande, participant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE formation (id INT AUTO_INCREMENT NOT NULL, smartof_uid VARCHAR(36) NOT NULL, reference VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, intitule VARCHAR(255) NOT NULL, duree_affichee VARCHAR(100) NOT NULL, duree_heures DOUBLE PRECISION DEFAULT NULL, objectifs LONGTEXT NOT NULL, prerequis LONGTEXT NOT NULL, public_vise LONGTEXT NOT NULL, prix_ht_centimes INT DEFAULT NULL, taux_tva DOUBLE PRECISION NOT NULL, eligible_cpf TINYINT NOT NULL, active TINYINT NOT NULL, synchronise_le DATETIME NOT NULL, UNIQUE INDEX UNIQ_404021BF4570026E (smartof_uid), UNIQUE INDEX UNIQ_404021BF989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE session_formation (id INT AUTO_INCREMENT NOT NULL, smartof_uid VARCHAR(36) NOT NULL, nom VARCHAR(255) NOT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME NOT NULL, lieu VARCHAR(255) NOT NULL, limite_places INT DEFAULT NULL, inscrits_smartof INT NOT NULL, ouverte TINYINT NOT NULL, synchronise_le DATETIME NOT NULL, formation_id INT NOT NULL, UNIQUE INDEX UNIQ_3A264B54570026E (smartof_uid), INDEX IDX_3A264B552EBEEF5 (date_debut), INDEX IDX_3A264B55200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commande (id INT AUTO_INCREMENT NOT NULL, numero VARCHAR(20) NOT NULL, jeton VARCHAR(32) NOT NULL, statut VARCHAR(255) NOT NULL, financement VARCHAR(255) NOT NULL, mode_paiement VARCHAR(255) NOT NULL, identifiant_france_travail VARCHAR(12) NOT NULL, remarque LONGTEXT NOT NULL, nb_participants INT NOT NULL, prix_unitaire_ht_centimes INT NOT NULL, taux_tva DOUBLE PRECISION NOT NULL, total_ht_centimes INT NOT NULL, total_ttc_centimes INT NOT NULL, cree_le DATETIME NOT NULL, expire_le DATETIME DEFAULT NULL, validee_le DATETIME DEFAULT NULL, cgv_acceptees_le DATETIME NOT NULL, paiement_details JSON NOT NULL, transmise_le DATETIME DEFAULT NULL, smartof_commanditaire_uid VARCHAR(36) DEFAULT NULL, tentatives_transmission INT NOT NULL, prochaine_tentative_le DATETIME DEFAULT NULL, derniere_erreur LONGTEXT NOT NULL, contact_prenom VARCHAR(100) NOT NULL, contact_nom VARCHAR(100) NOT NULL, contact_email VARCHAR(180) NOT NULL, contact_telephone VARCHAR(30) NOT NULL, societe_raison_sociale VARCHAR(255) NOT NULL, societe_adresse VARCHAR(255) NOT NULL, societe_code_postal VARCHAR(5) NOT NULL, societe_ville VARCHAR(120) NOT NULL, societe_siret VARCHAR(14) NOT NULL, societe_ape VARCHAR(6) NOT NULL, societe_tva_intracom VARCHAR(20) NOT NULL, societe_nb_salaries INT NOT NULL, societe_opco VARCHAR(100) NOT NULL, societe_organisation_professionnelle VARCHAR(50) NOT NULL, societe_dirigeant_prenom VARCHAR(100) NOT NULL, societe_dirigeant_nom VARCHAR(100) NOT NULL, societe_telephone VARCHAR(30) NOT NULL, societe_email VARCHAR(180) NOT NULL, session_id INT NOT NULL, UNIQUE INDEX UNIQ_6EEAA67DF55AE19E (numero), UNIQUE INDEX UNIQ_6EEAA67D2CF647B (jeton), INDEX IDX_6EEAA67DE564F0BF (statut), INDEX IDX_6EEAA67D613FECDF (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE participant (id INT AUTO_INCREMENT NOT NULL, prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, date_naissance DATE NOT NULL, situation VARCHAR(50) NOT NULL, numero_secu_chiffre LONGTEXT NOT NULL, smartof_apprenant_uid VARCHAR(36) DEFAULT NULL, commande_id INT NOT NULL, INDEX IDX_D79F6B1182EA2E54 (commande_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE session_formation ADD CONSTRAINT FK_3A264B55200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67D613FECDF FOREIGN KEY (session_id) REFERENCES session_formation (id)');
        $this->addSql('ALTER TABLE participant ADD CONSTRAINT FK_D79F6B1182EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participant DROP FOREIGN KEY FK_D79F6B1182EA2E54');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67D613FECDF');
        $this->addSql('ALTER TABLE session_formation DROP FOREIGN KEY FK_3A264B55200282E');
        $this->addSql('DROP TABLE participant');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE session_formation');
        $this->addSql('DROP TABLE formation');
    }

    public function isTransactional(): bool
    {
        return false; // DDL MySQL : commit implicite
    }
}
