<?php

namespace App\Command;

use App\Repository\SecurityAuditRepository;
use App\Service\SecurityAuditService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:security:audit',
    description: 'Exécute un audit de sécurité complet du système',
)]
class SecurityAuditCommand extends Command
{
    public function __construct(
        private readonly SecurityAuditService $auditService,
        private readonly SecurityAuditRepository $auditRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Tenter de corriger automatiquement les problèmes')
            ->addOption('report', null, InputOption::VALUE_NONE, 'Générer un rapport détaillé');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fix = $input->getOption('fix');

        $io->title('🔍 Audit de Sécurité OrphaMap');
        $io->info('Début de l\'audit...');

        try {
            // Exécuter l'audit complet
            $results = $this->auditService->runFullAudit();
            
            // Afficher les résultats
            $this->displayResults($io, $results);

            // Compter les problèmes
            $totalIssues = 0;
            $criticalIssues = 0;
            $highIssues = 0;
            $mediumIssues = 0;

            foreach ($results as $category => $issues) {
                foreach ($issues as $issue) {
                    $totalIssues++;
                    if ($issue['severity'] === 'critical') $criticalIssues++;
                    elseif ($issue['severity'] === 'high') $highIssues++;
                    elseif ($issue['severity'] === 'medium') $mediumIssues++;
                }
            }

            // Résumé
            $io->section('📊 Résumé');
            $io->table(
                ['Sévérité', 'Nombre'],
                [
                    ['🔴 Critique', $criticalIssues],
                    ['🟠 Élevé', $highIssues],
                    ['🟡 Moyen', $mediumIssues],
                    ['📊 Total', $totalIssues],
                ]
            );

            if ($totalIssues === 0) {
                $io->success('✅ Aucun problème de sécurité détecté !');
                return Command::SUCCESS;
            }

            // Sauvegarder les résultats dans la base de données
            $this->saveAuditResults($results);

            if ($fix) {
                $io->section('🔧 Correction automatique');
                $this->attemptFixes($io, $results);
            }

            if ($totalIssues > 0) {
                $io->warning("⚠️  {$totalIssues} problème(s) de sécurité détecté(s)");
                if ($criticalIssues > 0) {
                    $io->error("🔴 {$criticalIssues} problème(s) critique(s) nécessitent une attention immédiate !");
                }
            }

            return $totalIssues > 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors de l\'audit : ' . $e->getMessage());
            $this->auditService->logAudit('error', 'critical', 'Erreur lors de l\'exécution de l\'audit', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }

    private function displayResults(SymfonyStyle $io, array $results): void
    {
        $io->section('📋 Résultats détaillés');

        foreach ($results as $category => $issues) {
            if (empty($issues)) {
                $io->text("✓ <info>{$category}</info> : Aucun problème");
                continue;
            }

            $io->text("\n<error>✗ {$category}</error> : " . count($issues) . " problème(s)");
            
            foreach ($issues as $issue) {
                $severity = match($issue['severity']) {
                    'critical' => '<error>CRITIQUE</error>',
                    'high' => '<error>ÉLEVÉ</error>',
                    'medium' => '<comment>MOYEN</comment>',
                    'low' => '<info>FAIBLE</info>',
                    default => '<info>INFO</info>'
                };

                $io->text("  {$severity}");
                
                if (isset($issue['message'])) {
                    $io->text("    → {$issue['message']}");
                }
                if (isset($issue['path'])) {
                    $io->text("    → Fichier : {$issue['path']}");
                }
                if (isset($issue['file'])) {
                    $io->text("    → Fichier : {$issue['file']}");
                }
            }
        }
    }

    private function saveAuditResults(array $results): void
    {
        foreach ($results as $category => $issues) {
            foreach ($issues as $issue) {
                $message = $issue['message'] ?? "Problème détecté dans {$category}";
                
                $this->auditService->logAudit(
                    'security_audit',
                    $issue['severity'],
                    $message,
                    array_merge($issue, ['category' => $category])
                );
            }
        }
    }

    private function attemptFixes(SymfonyStyle $io, array $results): void
    {
        $fixed = 0;

        // Corriger les permissions de fichiers
        if (!empty($results['file_permissions'])) {
            foreach ($results['file_permissions'] as $issue) {
                if (isset($issue['path']) && file_exists($issue['path'])) {
                    $expectedPerm = octdec($issue['expected']);
                    if (chmod($issue['path'], $expectedPerm)) {
                        $io->text("✓ Permissions corrigées : {$issue['path']}");
                        $fixed++;
                    }
                }
            }
        }

        if ($fixed > 0) {
            $io->success("{$fixed} problème(s) corrigé(s) automatiquement");
        } else {
            $io->info('Aucune correction automatique disponible pour ces problèmes');
        }
    }
}