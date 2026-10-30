<?php

namespace App\Controller\Admin;

use App\Repository\SecurityAuditRepository;
use App\Service\SecurityAuditService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/security', name: 'admin_security_')]
class SecurityAuditController extends AbstractController
{
    public function __construct(
        private readonly SecurityAuditService $auditService,
        private readonly SecurityAuditRepository $auditRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/security/index.html.twig', [
            'unresolved_issues' => $this->auditService->getUnresolvedIssues(),
            'statistics' => $this->auditRepository->getStatistics(30),
            'recent_audits' => $this->auditRepository->findRecent(7, 50),
        ]);
    }

    #[Route('/audit/run', name: 'audit_run', methods: ['POST'])]
    public function runAudit(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('audit_run', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le formulaire a expiré. Veuillez réessayer.');

            return $this->redirectToRoute('admin_security_index');
        }

        try {
            $total = array_sum(array_map('count', $this->auditService->runFullAudit()));
            $this->addFlash($total > 0 ? 'info' : 'success', $total > 0
                ? sprintf('Audit terminé : %d point(s) à examiner.', $total)
                : 'Audit terminé : aucun problème détecté.');
        } catch (\Throwable $e) {
            $this->logger->error('Échec de l’audit de sécurité', ['exception' => $e]);
            $this->addFlash('error', 'L’audit a échoué. Le détail technique est consigné dans le journal.');
        }

        return $this->redirectToRoute('admin_security_index');
    }

    #[Route('/issue/{id}/resolve', name: 'issue_resolve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function resolveIssue(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('issue_resolve_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le formulaire a expiré. Veuillez réessayer.');

            return $this->redirectToRoute('admin_security_index');
        }

        $this->auditService->resolveIssue($id);
        $this->addFlash('success', 'Problème marqué comme résolu.');

        return $this->redirectToRoute('admin_security_index');
    }
}
