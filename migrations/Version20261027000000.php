<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Finalisation du modèle de données OrphaMap.
 *
 * - convertit les tables en InnoDB (clés étrangères et transactions) ;
 * - rattache chaque orphelinat directement à sa commune (le quartier devient facultatif) ;
 * - ajoute statut, vérification, coordonnées, contacts web et dates aux orphelinats ;
 * - complète le circuit des propositions (source, motif de refus, suivi, lien vers la fiche) ;
 * - crée les tables des signalements et des photos ;
 * - ajoute les contraintes d'unicité du référentiel géographique.
 *
 * Aucune donnée n'est supprimée : la colonne orphelinat.clear n'est retirée que si
 * elle est vide partout, et les références de commune invalides des propositions
 * (identifiants inexistants) sont remises à NULL, les libellés texte étant conservés.
 * La migration est idempotente vis-à-vis des clés et index déjà présents.
 */
final class Version20261027000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'InnoDB, statuts, vérification, signalements, photos, coordonnées et contraintes';
    }

    public function isTransactional(): bool
    {
        // Les ALTER TABLE MySQL valident implicitement la transaction.
        return false;
    }

    public function preUp(Schema $schema): void
    {
        if ($this->columnExists('orphelinat', 'clear')) {
            $nonEmpty = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM orphelinat WHERE clear IS NOT NULL AND clear <> ''");
            $this->abortIf($nonEmpty > 0, 'La colonne orphelinat.clear contient des données : migration interrompue pour ne rien perdre.');
        }

        if ($this->columnExists('orphelinat', 'quartier_id') && !$this->columnExists('orphelinat', 'commune_id')) {
            $orphans = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM orphelinat o LEFT JOIN quartier q ON q.id = o.quartier_id WHERE q.id IS NULL');
            $this->abortIf($orphans > 0, sprintf('%d orphelinat(s) référencent un quartier inexistant : impossible de déduire leur commune.', $orphans));
        }

        foreach ([
            'pays' => 'nom',
            'ville' => 'pays_id, nom',
            'commune' => 'ville_id, nom',
            'quartier' => 'commune_id, nom',
        ] as $table => $columns) {
            $duplicates = (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM (SELECT 1 FROM %s GROUP BY %s HAVING COUNT(*) > 1) d', $table, $columns));
            $this->abortIf($duplicates > 0, sprintf('Doublons dans la table %s : fusionnez-les avant de migrer.', $table));
        }
    }

    public function up(Schema $schema): void
    {
        // 1. Moteur InnoDB partout
        $tables = $this->connection->fetchAllAssociative("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
        foreach ($tables as $table) {
            if ('InnoDB' !== $table['ENGINE']) {
                $this->addSql(sprintf('ALTER TABLE `%s` ENGINE = InnoDB', $table['TABLE_NAME']));
            }
        }

        // 2. Table d'audit (déclarée migrée sur certaines bases sans avoir été créée)
        $this->addSql('CREATE TABLE IF NOT EXISTS security_audit (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, type VARCHAR(50) NOT NULL, severity VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, metadata JSON DEFAULT NULL, resolved TINYINT NOT NULL, resolved_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE security_audit MODIFY resolved TINYINT NOT NULL');

        // 3. Nouvelles tables
        $this->addSql('CREATE TABLE orphelinat_photo (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, orphelinat_id INT NOT NULL, INDEX IDX_331287F2B6FB23E2 (orphelinat_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE signalement (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, message LONGTEXT NOT NULL, contact VARCHAR(180) DEFAULT NULL, statut VARCHAR(20) NOT NULL, decision LONGTEXT DEFAULT NULL, traite_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, orphelinat_id INT NOT NULL, INDEX IDX_F4B55114B6FB23E2 (orphelinat_id), INDEX idx_signalement_statut (statut), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // 4. Orphelinats : colonnes ajoutées d'abord nullables, remplies, puis contraintes
        $this->addSql("ALTER TABLE orphelinat ADD site_web VARCHAR(255) DEFAULT NULL, ADD reseau_social VARCHAR(255) DEFAULT NULL, ADD services LONGTEXT DEFAULT NULL, ADD latitude NUMERIC(10, 7) DEFAULT NULL, ADD longitude NUMERIC(10, 7) DEFAULT NULL, ADD statut VARCHAR(20) NOT NULL DEFAULT 'actif', ADD verification VARCHAR(20) NOT NULL DEFAULT 'non_verifie', ADD verified_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD commune_id INT DEFAULT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE telephone telephone VARCHAR(50) DEFAULT NULL, CHANGE email email VARCHAR(180) DEFAULT NULL, CHANGE image image VARCHAR(255) DEFAULT NULL, CHANGE document document VARCHAR(255) DEFAULT NULL, CHANGE quartier_id quartier_id INT DEFAULT NULL");
        if ($this->columnExists('orphelinat', 'clear')) {
            $this->addSql('ALTER TABLE orphelinat DROP clear');
        }
        $this->addSql('UPDATE orphelinat o INNER JOIN quartier q ON q.id = o.quartier_id SET o.commune_id = q.commune_id');
        $this->addSql('UPDATE orphelinat SET created_at = NOW() WHERE created_at IS NULL');
        // Valeurs vides historiques -> NULL (aucune information perdue)
        $this->addSql("UPDATE orphelinat SET image = NULL WHERE image = ''");
        $this->addSql("UPDATE orphelinat SET document = NULL WHERE document = ''");
        $this->addSql("UPDATE orphelinat SET email = NULL WHERE email = ''");
        $this->addSql("UPDATE orphelinat SET telephone = NULL WHERE telephone = ''");
        $this->addSql('ALTER TABLE orphelinat CHANGE commune_id commune_id INT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE statut statut VARCHAR(20) NOT NULL, CHANGE verification verification VARCHAR(20) NOT NULL');

        // 5. Propositions
        $this->addSql('ALTER TABLE proposition_orphelinat ADD source LONGTEXT DEFAULT NULL, ADD commune_saisie VARCHAR(255) DEFAULT NULL, ADD motif_refus LONGTEXT DEFAULT NULL, ADD note_admin LONGTEXT DEFAULT NULL, ADD traitee_at DATETIME DEFAULT NULL, ADD suivi_token VARCHAR(64) DEFAULT NULL, ADD orphelinat_id INT DEFAULT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE telephone telephone VARCHAR(50) DEFAULT NULL, CHANGE email email VARCHAR(180) DEFAULT NULL, CHANGE image image VARCHAR(255) DEFAULT NULL, CHANGE document document VARCHAR(255) DEFAULT NULL, CHANGE quartier quartier VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(20) NOT NULL, CHANGE commune_id commune_id INT DEFAULT NULL');
        $this->addSql("UPDATE proposition_orphelinat SET source = 'Non renseignée (proposition antérieure à ce champ)' WHERE source IS NULL");
        $this->addSql('UPDATE proposition_orphelinat SET suivi_token = LOWER(SHA2(CONCAT(UUID(), RAND(), id), 256)) WHERE suivi_token IS NULL');
        $this->addSql("UPDATE proposition_orphelinat SET statut = 'acceptee', traitee_at = created_at WHERE statut = 'valide'");
        $this->addSql("UPDATE proposition_orphelinat SET statut = 'refusee', traitee_at = created_at WHERE statut = 'refuse'");
        $this->addSql('UPDATE proposition_orphelinat p LEFT JOIN commune c ON c.id = p.commune_id SET p.commune_id = NULL WHERE c.id IS NULL');
        $this->addSql("UPDATE proposition_orphelinat SET image = NULL WHERE image = ''");
        $this->addSql("UPDATE proposition_orphelinat SET document = NULL WHERE document = ''");
        $this->addSql("UPDATE proposition_orphelinat SET email = NULL WHERE email = ''");
        $this->addSql("UPDATE proposition_orphelinat SET telephone = NULL WHERE telephone = ''");
        $this->addSql('ALTER TABLE proposition_orphelinat CHANGE source source LONGTEXT NOT NULL, CHANGE suivi_token suivi_token VARCHAR(64) NOT NULL');

        // 7. Index et contraintes d'unicité
        $this->createIndexIfMissing('orphelinat', 'IDX_2228FA0F131A4F72', 'INDEX', 'commune_id');
        $this->createIndexIfMissing('orphelinat', 'idx_orphelinat_statut', 'INDEX', 'statut');
        $this->createIndexIfMissing('proposition_orphelinat', 'IDX_A5CAEC1131A4F72', 'INDEX', 'commune_id');
        $this->createIndexIfMissing('proposition_orphelinat', 'IDX_A5CAEC1B6FB23E2', 'INDEX', 'orphelinat_id');
        $this->createIndexIfMissing('proposition_orphelinat', 'idx_proposition_statut', 'INDEX', 'statut');
        $this->createIndexIfMissing('proposition_orphelinat', 'UNIQ_A5CAEC1FC3B8118', 'UNIQUE INDEX', 'suivi_token');
        $this->createIndexIfMissing('pays', 'uniq_pays_nom', 'UNIQUE INDEX', 'nom');
        $this->createIndexIfMissing('ville', 'uniq_ville_pays_nom', 'UNIQUE INDEX', 'pays_id, nom');
        $this->createIndexIfMissing('commune', 'uniq_commune_ville_nom', 'UNIQUE INDEX', 'ville_id, nom');
        $this->createIndexIfMissing('quartier', 'uniq_quartier_commune_nom', 'UNIQUE INDEX', 'commune_id, nom');

        // 6. Clés étrangères (recréées si elles existent avec une autre règle)
        $this->replaceForeignKey('commune', 'FK_E2E2D1EEA73F0036', 'FOREIGN KEY (ville_id) REFERENCES ville (id)');
        $this->replaceForeignKey('ville', 'FK_43C3D9C3A6E44244', 'FOREIGN KEY (pays_id) REFERENCES pays (id)');
        $this->replaceForeignKey('quartier', 'FK_FEE8962D131A4F72', 'FOREIGN KEY (commune_id) REFERENCES commune (id)');
        $this->replaceForeignKey('orphelinat', 'FK_2228FA0FDF1E57AB', 'FOREIGN KEY (quartier_id) REFERENCES quartier (id) ON DELETE SET NULL');
        $this->replaceForeignKey('orphelinat', 'FK_2228FA0F131A4F72', 'FOREIGN KEY (commune_id) REFERENCES commune (id)');
        $this->replaceForeignKey('proposition_orphelinat', 'FK_A5CAEC1131A4F72', 'FOREIGN KEY (commune_id) REFERENCES commune (id) ON DELETE SET NULL');
        $this->replaceForeignKey('proposition_orphelinat', 'FK_A5CAEC1B6FB23E2', 'FOREIGN KEY (orphelinat_id) REFERENCES orphelinat (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE orphelinat_photo ADD CONSTRAINT FK_331287F2B6FB23E2 FOREIGN KEY (orphelinat_id) REFERENCES orphelinat (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE signalement ADD CONSTRAINT FK_F4B55114B6FB23E2 FOREIGN KEY (orphelinat_id) REFERENCES orphelinat (id) ON DELETE CASCADE');

    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Restaurer la sauvegarde SQL prise avant la migration (var/backups/).');
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) > 0;
    }

    private function replaceForeignKey(string $table, string $name, string $definition): void
    {
        $exists = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $name]
        );
        if ($exists > 0) {
            $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $name));
        }
        $this->addSql(sprintf('ALTER TABLE %s ADD CONSTRAINT %s %s', $table, $name, $definition));
    }

    private function createIndexIfMissing(string $table, string $name, string $type, string $columns): void
    {
        $exists = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $name]
        );
        if (0 === $exists) {
            $this->addSql(sprintf('CREATE %s %s ON %s (%s)', $type, $name, $table, $columns));
        }
    }
}
