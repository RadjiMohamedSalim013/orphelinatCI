<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class BackupService
{
    private string $backupDir;
    private string $uploadsDir;
    private Filesystem $filesystem;

    public function __construct(ParameterBagInterface $params, private readonly Connection $connection)
    {
        $this->backupDir = $params->get('kernel.project_dir') . '/var/backups';
        $this->uploadsDir = $params->get('kernel.project_dir') . '/public/uploads';
        $this->filesystem = new Filesystem();

        // Créer le dossier de backup s'il n'existe pas
        if (!$this->filesystem->exists($this->backupDir)) {
            $this->filesystem->mkdir($this->backupDir, 0755);
        }
    }

    public function backupDatabase(): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $this->backupDir . "/db_backup_{$timestamp}.sql.gz";

        // Récupérer les paramètres de la base de données
        $dbParams = $this->getDatabaseParams();

        // Le mot de passe passe par MYSQL_PWD, jamais par la ligne de commande
        // (visible de tous les processus de la machine).
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction %s | gzip > %s',
            escapeshellarg($dbParams['host']),
            escapeshellarg($dbParams['port']),
            escapeshellarg($dbParams['user']),
            escapeshellarg($dbParams['dbname']),
            escapeshellarg($backupFile)
        );

        $process = Process::fromShellCommandline($command, null, ['MYSQL_PWD' => $dbParams['password']]);
        $process->setTimeout(300); // 5 minutes timeout
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Erreur lors de la sauvegarde de la base de données: ' . $process->getErrorOutput());
        }

        return $backupFile;
    }

    public function backupUploadedFiles(): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $this->backupDir . "/files_backup_{$timestamp}.tar.gz";

        if (!$this->filesystem->exists($this->uploadsDir)) {
            throw new \RuntimeException('Le dossier uploads n\'existe pas');
        }

        // Créer une archive tar.gz des fichiers uploadés
        $command = sprintf(
            'tar -czf %s -C %s .',
            escapeshellarg($backupFile),
            escapeshellarg($this->uploadsDir)
        );

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Erreur lors de la sauvegarde des fichiers: ' . $process->getErrorOutput());
        }

        return $backupFile;
    }

    public function cleanOldBackups(int $daysToKeep = 30): int
    {
        $deleted = 0;
        $cutoffDate = time() - ($daysToKeep * 24 * 60 * 60);

        if (!$this->filesystem->exists($this->backupDir)) {
            return 0;
        }

        $files = glob($this->backupDir . '/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                $fileTime = filemtime($file);
                if ($fileTime < $cutoffDate) {
                    $this->filesystem->remove($file);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private function getDatabaseParams(): array
    {
        // Paramètres de la connexion Doctrine (issus de DATABASE_URL), ceux réellement utilisés par le site
        $params = $this->connection->getParams();

        return [
            'host' => (string) ($params['host'] ?? 'localhost'),
            'port' => (string) ($params['port'] ?? '3306'),
            'user' => (string) ($params['user'] ?? 'root'),
            'password' => (string) ($params['password'] ?? ''),
            'dbname' => (string) ($params['dbname'] ?? 'orphamap'),
        ];
    }

    public function getBackupList(): array
    {
        $backups = [];

        if (!$this->filesystem->exists($this->backupDir)) {
            return $backups;
        }

        $files = glob($this->backupDir . '/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                $backups[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => $this->formatBytes(filesize($file)),
                    'date' => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
        }

        // Trier par date décroissante
        usort($backups, function ($a, $b) {
            return strtotime($b['date']) <=> strtotime($a['date']);
        });

        return $backups;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}