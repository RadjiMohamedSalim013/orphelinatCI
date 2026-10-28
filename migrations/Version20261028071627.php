<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028071627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Statistiques d’audience internes (sans cookie)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stat_compteur (id INT AUTO_INCREMENT NOT NULL, jour DATE NOT NULL, cle VARCHAR(150) NOT NULL, total INT DEFAULT 0 NOT NULL, INDEX idx_stat_compteur_cle (cle), UNIQUE INDEX uniq_stat_compteur (jour, cle), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stat_jour (id INT AUTO_INCREMENT NOT NULL, jour DATE NOT NULL, vues INT DEFAULT 0 NOT NULL, visiteurs INT DEFAULT 0 NOT NULL, mobile INT DEFAULT 0 NOT NULL, tablette INT DEFAULT 0 NOT NULL, ordinateur INT DEFAULT 0 NOT NULL, UNIQUE INDEX uniq_stat_jour (jour), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stat_visiteur (id INT AUTO_INCREMENT NOT NULL, jour DATE NOT NULL, empreinte VARCHAR(64) NOT NULL, UNIQUE INDEX uniq_stat_visiteur (jour, empreinte), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE stat_compteur');
        $this->addSql('DROP TABLE stat_jour');
        $this->addSql('DROP TABLE stat_visiteur');
    }
}
