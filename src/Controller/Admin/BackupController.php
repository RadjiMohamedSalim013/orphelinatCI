<?php

namespace App\Controller\Admin;

use App\Service\BackupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[IsGranted('ROLE_ADMIN')]
#[Route('/backups', name: 'admin_backups_')]
class BackupController extends AbstractController
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly ParameterBagInterface $params
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $backups = $this->backupService->getBackupList();
        
        return $this->render('admin/backups/index.html.twig', [
            'backups' => $backups,
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(): JsonResponse
    {
        try {
            $dbBackup = $this->backupService->backupDatabase();
            $filesBackup = $this->backupService->backupUploadedFiles();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Sauvegarde créée avec succès',
                'db_backup' => basename($dbBackup),
                'files_backup' => basename($filesBackup),
            ]);
        } catch (\Exception $e) {
            $errorMessage = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde: ' . $errorMessage,
            ], 500);
        }
    }

    #[Route('/clean', name: 'clean', methods: ['POST'])]
    public function clean(): JsonResponse
    {
        try {
            $deleted = $this->backupService->cleanOldBackups(30);
            
            return new JsonResponse([
                'success' => true,
                'message' => "{$deleted} ancienne(s) sauvegarde(s) supprimée(s)",
            ]);
        } catch (\Exception $e) {
            $errorMessage = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors du nettoyage: ' . $errorMessage,
            ], 500);
        }
    }

    #[Route('/download/{filename}', name: 'download', methods: ['GET'], requirements: ['filename' => '[A-Za-z0-9][A-Za-z0-9._-]*'])]
    public function download(string $filename): Response
    {
        $backupDir = $this->params->get('kernel.project_dir') . '/var/backups';
        $filePath = $backupDir . '/' . basename($filename);

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Fichier de sauvegarde introuvable');
        }

        return $this->file($filePath, $filename, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }
}
