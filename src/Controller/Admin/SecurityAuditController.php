<?php

namespace App\Controller\Admin;

use App\Repository\SecurityAuditRepository;
use App\Service\SecurityAuditService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[IsGranted('ROLE_ADMIN')]
#[Route('/security', name: 'admin_security_')]
class SecurityAuditController extends AbstractController
{
    public function __construct(
        private readonly SecurityAuditService $auditService,
        private readonly SecurityAuditRepository $auditRepository
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $unresolvedIssues = $this->auditService->getUnresolvedIssues();
        $statistics = $this->auditRepository->getStatistics(30);
        $recentAudits = $this->auditRepository->findRecent(7, 50);
        
        return $this->render('admin/security/index.html.twig', [
            'unresolved_issues' => $unresolvedIssues,
            'statistics' => $statistics,
            'recent_audits' => $recentAudits,
        ]);
    }

    #[Route('/audit/run', name: 'audit_run', methods: ['POST'])]
    public function runAudit(): JsonResponse
    {
        try {
            $results = $this->auditService->runFullAudit();
            
            $totalIssues = 0;
            foreach ($results as $issues) {
                $totalIssues += count($issues);
            }
            
            return new JsonResponse([
                'success' => true,
                'message' => "Audit terminé : {$totalIssues} problème(s) détecté(s)",
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'audit: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/issue/{id}/resolve', name: 'issue_resolve', methods: ['POST'])]
    public function resolveIssue(int $id): JsonResponse
    {
        try {
            $this->auditService->resolveIssue($id);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Problème marqué comme résolu',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }
}
