<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\MimeTypes;

/**
 * Sauvegarde des fichiers envoyés dans la base de données (activée par
 * FICHIERS_EN_BASE=1) et restauration sur le disque quand ils y manquent.
 *
 * Les accès passent par DBAL pour ne pas charger les contenus binaires dans
 * l'unité de travail Doctrine des requêtes métier.
 */
class CoffreFichiers
{
    /** @var array<string, bool> */
    private array $presence = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly bool $fichiersEnBase,
    ) {
    }

    public function estActif(): bool
    {
        return $this->fichiersEnBase;
    }

    public function sauver(string $chemin, string $cheminDisque): void
    {
        if (!$this->fichiersEnBase || !is_file($cheminDisque)) {
            return;
        }

        try {
            $this->connection->executeStatement(
                'INSERT INTO fichier_stocke (chemin, type_mime, contenu, taille, created_at) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE type_mime = VALUES(type_mime), contenu = VALUES(contenu), taille = VALUES(taille)',
                [
                    $chemin,
                    MimeTypes::getDefault()->guessMimeType($cheminDisque) ?? 'application/octet-stream',
                    file_get_contents($cheminDisque),
                    filesize($cheminDisque),
                    (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ],
                [3 => \Doctrine\DBAL\ParameterType::LARGE_OBJECT],
            );
            $this->presence[$chemin] = true;
        } catch (\Throwable $e) {
            // Le fichier reste disponible sur le disque : on journalise sans bloquer l'envoi.
            $this->logger->error('Copie en base du fichier impossible.', ['chemin' => $chemin, 'exception' => $e]);
        }
    }

    public function existe(string $chemin): bool
    {
        return $this->presence[$chemin] ??= (bool) $this->connection->fetchOne('SELECT 1 FROM fichier_stocke WHERE chemin = ?', [$chemin]);
    }

    /**
     * Réécrit le fichier sur le disque depuis la base. Retourne true si le fichier est désormais présent.
     */
    public function restaurer(string $chemin, string $cheminDisque): bool
    {
        if (is_file($cheminDisque)) {
            return true;
        }

        $contenu = $this->connection->fetchOne('SELECT contenu FROM fichier_stocke WHERE chemin = ?', [$chemin]);
        if (false === $contenu || null === $contenu) {
            return false;
        }

        if (!is_dir(dirname($cheminDisque))) {
            mkdir(dirname($cheminDisque), 0775, true);
        }

        return false !== file_put_contents($cheminDisque, is_resource($contenu) ? stream_get_contents($contenu) : $contenu, LOCK_EX);
    }

    public function supprimer(string $chemin): void
    {
        if ($this->fichiersEnBase) {
            $this->connection->executeStatement('DELETE FROM fichier_stocke WHERE chemin = ?', [$chemin]);
            unset($this->presence[$chemin]);
        }
    }

    public static function chemin(string $type, string $filename): string
    {
        return $type.'/'.basename(str_replace('\\', '/', $filename));
    }
}
