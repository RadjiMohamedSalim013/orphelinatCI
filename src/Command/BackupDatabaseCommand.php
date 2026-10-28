<?php

namespace App\Command;

use App\Service\BackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backup:database',
    description: 'Crée une sauvegarde de la base de données et des fichiers uploadés',
)]
class BackupDatabaseCommand extends Command
{
    public function __construct(
        private readonly BackupService $backupService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Début de la sauvegarde...');

        try {
            // Sauvegarde de la base de données
            $io->section('Sauvegarde de la base de données');
            $dbBackup = $this->backupService->backupDatabase();
            $io->success("Base de données sauvegardée : {$dbBackup}");

            // Sauvegarde des fichiers uploadés
            $io->section('Sauvegarde des fichiers uploadés');
            $filesBackup = $this->backupService->backupUploadedFiles();
            $io->success("Fichiers sauvegardés : {$filesBackup}");

            // Nettoyage des vieilles sauvegardes (garder les 30 derniers jours)
            $io->section('Nettoyage des anciennes sauvegardes');
            $deleted = $this->backupService->cleanOldBackups(30);
            $io->success("{$deleted} anciennes sauvegardes supprimées");

            $io->success('Sauvegarde terminée avec succès !');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors de la sauvegarde : ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}