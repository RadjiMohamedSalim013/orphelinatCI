<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027034813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Copie en base des fichiers envoyés (hébergement au disque éphémère)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fichier_stocke (id INT AUTO_INCREMENT NOT NULL, chemin VARCHAR(255) NOT NULL, type_mime VARCHAR(100) NOT NULL, contenu LONGBLOB NOT NULL, taille INT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_fichier_chemin (chemin), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE fichier_stocke');
    }
}
