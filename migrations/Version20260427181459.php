<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260427181459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commune (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, ville_id INT NOT NULL, INDEX IDX_E2E2D1EEA73F0036 (ville_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE orphelinat (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, image VARCHAR(255) NOT NULL, document VARCHAR(255) NOT NULL, clear VARCHAR(255) NOT NULL, quartier_id INT NOT NULL, INDEX IDX_2228FA0FDF1E57AB (quartier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE pays (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE proposition_orphelinat (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, adresse VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, image VARCHAR(255) NOT NULL, document VARCHAR(255) NOT NULL, nom_proposant VARCHAR(255) NOT NULL, contact_proposant VARCHAR(255) NOT NULL, pays VARCHAR(255) NOT NULL, ville VARCHAR(255) NOT NULL, quartier VARCHAR(255) NOT NULL, statut VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE quartier (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, commune_id INT NOT NULL, INDEX IDX_FEE8962D131A4F72 (commune_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE ville (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, pays_id INT NOT NULL, INDEX IDX_43C3D9C3A6E44244 (pays_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE commune ADD CONSTRAINT FK_E2E2D1EEA73F0036 FOREIGN KEY (ville_id) REFERENCES ville (id)');
        $this->addSql('ALTER TABLE orphelinat ADD CONSTRAINT FK_2228FA0FDF1E57AB FOREIGN KEY (quartier_id) REFERENCES quartier (id)');
        $this->addSql('ALTER TABLE quartier ADD CONSTRAINT FK_FEE8962D131A4F72 FOREIGN KEY (commune_id) REFERENCES commune (id)');
        $this->addSql('ALTER TABLE ville ADD CONSTRAINT FK_43C3D9C3A6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commune DROP FOREIGN KEY FK_E2E2D1EEA73F0036');
        $this->addSql('ALTER TABLE orphelinat DROP FOREIGN KEY FK_2228FA0FDF1E57AB');
        $this->addSql('ALTER TABLE quartier DROP FOREIGN KEY FK_FEE8962D131A4F72');
        $this->addSql('ALTER TABLE ville DROP FOREIGN KEY FK_43C3D9C3A6E44244');
        $this->addSql('DROP TABLE commune');
        $this->addSql('DROP TABLE orphelinat');
        $this->addSql('DROP TABLE pays');
        $this->addSql('DROP TABLE proposition_orphelinat');
        $this->addSql('DROP TABLE quartier');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE ville');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
