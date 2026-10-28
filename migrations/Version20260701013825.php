<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Doublon historique de Version20260701013818, conservé pour la cohérence de
 * la table doctrine_migration_versions. Volontairement sans effet.
 */
final class Version20260701013825 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sans effet (doublon historique)';
    }

    public function up(Schema $schema): void
    {
        $this->write('Migration historique sans effet.');
    }

    public function down(Schema $schema): void
    {
        $this->write('Migration historique sans effet.');
    }
}
