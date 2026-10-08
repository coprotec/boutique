<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sociétés hors de France : pays (ISO 3166-1 alpha-2), codes postaux étrangers jusqu'à 10 caractères.
 * Les téléphones existants passent au format E.164, comme les nouvelles saisies.
 */
final class Version20261007000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pays de la société, codes postaux étrangers, téléphones en E.164';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE commande ADD societe_pays CHAR(2) DEFAULT 'FR' NOT NULL AFTER societe_ville, MODIFY societe_code_postal VARCHAR(10) NOT NULL");

        // Saisies françaises validées par l'ancienne regex : séparateurs retirés, 0X… ou +33X… → +33X…
        foreach (['contact_telephone', 'societe_telephone'] as $colonne) {
            $this->addSql("UPDATE commande SET $colonne = REGEXP_REPLACE($colonne, '[^0-9+]', '') WHERE $colonne NOT LIKE '+%' OR $colonne REGEXP '[^0-9+]'");
            $this->addSql("UPDATE commande SET $colonne = CONCAT('+33', SUBSTRING($colonne, 2)) WHERE $colonne REGEXP '^0[1-9][0-9]{8}$'");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP societe_pays, MODIFY societe_code_postal VARCHAR(5) NOT NULL');
    }
}
