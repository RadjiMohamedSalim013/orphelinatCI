<?php

namespace App\Controller\Admin;

use App\Service\BackupService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/backups', name: 'admin_backups_')]
class BackupController extends AbstractController
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly ParameterBagInterface $params,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/backups/index.html.twig', [
            'backups' => $this->backupService->getBackupList(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('backup_create', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le formulaire a expiré. Veuillez réessayer.');

            return $this->redirectToRoute('admin_backups_index');
        }

        try {
            $base = basename($this->backupService->backupDatabase());
            $fichiers = basename($this->backupService->backupUploadedFiles());
            $this->addFlash('success', sprintf('Sauvegarde créée : %s et %s.', $base, $fichiers));
        } catch (\Throwable $e) {
            $this->logger->error('Échec de la sauvegarde', ['exception' => $e]);
            $this->addFlash('error', 'La sauvegarde a échoué. Le détail technique est consigné dans le journal.');
        }

        return $this->redirectToRoute('admin_backups_index');
    }

    #[Route('/clean', name: 'clean', methods: ['POST'])]
    public function clean(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('backup_clean', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le formulaire a expiré. Veuillez réessayer.');

            return $this->redirectToRoute('admin_backups_index');
        }

        try {
            $supprimees = $this->backupService->cleanOldBackups(30);
            $this->addFlash($supprimees > 0 ? 'success' : 'info', $supprimees > 0
                ? sprintf('%d sauvegarde(s) de plus de 30 jours supprimée(s).', $supprimees)
                : 'Aucune sauvegarde de plus de 30 jours à supprimer.');
        } catch (\Throwable $e) {
            $this->logger->error('Échec du nettoyage des sauvegardes', ['exception' => $e]);
            $this->addFlash('error', 'Le nettoyage a échoué. Le détail technique est consigné dans le journal.');
        }

        return $this->redirectToRoute('admin_backups_index');
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
