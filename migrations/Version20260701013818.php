<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la colonne commune_id aux propositions.
 *
 * La version d'origine ajoutait des clés étrangères sur une colonne qu'aucune
 * migration ne créait (installation neuve impossible). Les clés étrangères sont
 * désormais gérées par Version20261027000000. Sur les bases où cette migration
 * est déjà marquée comme exécutée, ce fichier n'est pas rejoué.
 */
final class Version20260701013818 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute proposition_orphelinat.commune_id';
    }

    public function up(Schema $schema): void
    {
        $exists = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposition_orphelinat' AND COLUMN_NAME = 'commune_id'"
        );

        if (0 === $exists) {
            $this->addSql('ALTER TABLE proposition_orphelinat ADD commune_id INT DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_A5CAEC1131A4F72 ON proposition_orphelinat (commune_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_A5CAEC1131A4F72 ON proposition_orphelinat');
        $this->addSql('ALTER TABLE proposition_orphelinat DROP commune_id');
    }
}
