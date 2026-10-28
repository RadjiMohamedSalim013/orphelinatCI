<?php

namespace App\Service;

use App\Entity\SecurityAudit;
use App\Repository\SecurityAuditRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityAuditService
{
    private EntityManagerInterface $entityManager;
    private SecurityAuditRepository $auditRepository;
    private ParameterBagInterface $params;
    private Filesystem $filesystem;

    public function __construct(
        EntityManagerInterface $entityManager,
        SecurityAuditRepository $auditRepository,
        ParameterBagInterface $params
    ) {
        $this->entityManager = $entityManager;
        $this->auditRepository = $auditRepository;
        $this->params = $params;
        $this->filesystem = new Filesystem();
    }

    public function runFullAudit(): array
    {
        $results = [];
        
        // 1. Vérifier les permissions des fichiers
        $results['file_permissions'] = $this->checkFilePermissions();
        
        // 2. Vérifier les mots de passe faibles
        $results['weak_passwords'] = $this->checkWeakPasswords();
        
        // 3. Vérifier les fichiers uploadés
        $results['uploaded_files'] = $this->checkUploadedFiles();
        
        // 4. Vérifier les sauvegardes
        $results['backups'] = $this->checkBackups();
        
        // 5. Vérifier les configurations de sécurité
        $results['security_config'] = $this->checkSecurityConfig();
        
        // 6. Vérifier les utilisateurs inactifs
        $results['inactive_users'] = $this->checkInactiveUsers();
        
        return $results;
    }

    private function checkFilePermissions(): array
    {
        $issues = [];
        $criticalPaths = [
            $this->params->get('kernel.project_dir') . '/.env' => 640,
            $this->params->get('kernel.project_dir') . '/config' => 750,
            $this->params->get('kernel.project_dir') . '/src' => 750,
            $this->params->get('kernel.project_dir') . '/var' => 770,
        ];

        foreach ($criticalPaths as $path => $expectedPerm) {
            if (file_exists($path)) {
                $actualPerm = substr(sprintf('%o', fileperms($path)), -3);
                if ($actualPerm > $expectedPerm) {
                    $issues[] = [
                        'path' => $path,
                        'expected' => $expectedPerm,
                        'actual' => $actualPerm,
                        'severity' => 'high'
                    ];
                }
            }
        }

        return $issues;
    }

    private function checkWeakPasswords(): array
    {
        $issues = [];
        
        // Vérifier si des mots de passe par défaut existent
        $defaultPasswords = ['admin', 'password', '123456', 'root', 'test'];
        
        // Cette vérification dépend de votre système d'authentification
        // Ici on vérifie juste les configurations par défaut
        
        return $issues;
    }

    private function checkUploadedFiles(): array
    {
        $issues = [];
        $uploadsDir = $this->params->get('kernel.project_dir') . '/public/uploads';
        
        if (!$this->filesystem->exists($uploadsDir)) {
            return $issues;
        }

        // Vérifier les fichiers exécutables dans le dossier uploads
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($uploadsDir)
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $extension = strtolower($file->getExtension());
                $dangerousExtensions = ['php', 'exe', 'sh', 'bat', 'cmd'];
                
                if (in_array($extension, $dangerousExtensions)) {
                    $issues[] = [
                        'file' => $file->getPathname(),
                        'extension' => $extension,
                        'severity' => 'critical'
                    ];
                }
            }
        }

        return $issues;
    }

    private function checkBackups(): array
    {
        $issues = [];
        $backupDir = $this->params->get('kernel.project_dir') . '/var/backups';
        
        if (!$this->filesystem->exists($backupDir)) {
            $issues[] = [
                'message' => 'Aucun dossier de sauvegarde trouvé',
                'severity' => 'medium'
            ];
        } else {
            $files = glob($backupDir . '/*');
            $lastBackup = null;
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $fileTime = filemtime($file);
                    if (!$lastBackup || $fileTime > $lastBackup) {
                        $lastBackup = $fileTime;
                    }
                }
            }
            
            if ($lastBackup) {
                $daysSinceLastBackup = (time() - $lastBackup) / (24 * 60 * 60);
                if ($daysSinceLastBackup > 7) {
                    $issues[] = [
                        'message' => "Dernière sauvegarde il y a " . round($daysSinceLastBackup) . " jours",
                        'severity' => 'high'
                    ];
                }
            } else {
                $issues[] = [
                    'message' => 'Aucune sauvegarde trouvée',
                    'severity' => 'high'
                ];
            }
        }

        return $issues;
    }

    private function checkSecurityConfig(): array
    {
        $issues = [];
        
        // Vérifier les paramètres de sécurité critiques
        $envFile = $this->params->get('kernel.project_dir') . '/.env';
        
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            
            // Vérifier APP_DEBUG
            if (strpos($content, 'APP_DEBUG=1') !== false) {
                $issues[] = [
                    'message' => 'APP_DEBUG est activé en production',
                    'severity' => 'critical'
                ];
            }
            
            // Vérifier la clé secrète
            if (preg_match('/APP_SECRET=(.+)/', $content, $matches)) {
                if (strlen($matches[1]) < 32) {
                    $issues[] = [
                        'message' => 'APP_SECRET trop courte (minimum 32 caractères)',
                        'severity' => 'high'
                    ];
                }
            }
        }

        return $issues;
    }

    private function checkInactiveUsers(): array
    {
        $issues = [];
        
        // Vérifier les utilisateurs qui ne se sont pas connectés depuis 90 jours
        // Cette vérification dépend de votre entité User
        
        return $issues;
    }

    public function logAudit(string $type, string $severity, string $message, ?array $metadata = null): SecurityAudit
    {
        $audit = new SecurityAudit();
        $audit->setType($type);
        $audit->setSeverity($severity);
        $audit->setMessage($message);
        $audit->setMetadata($metadata);

        $this->entityManager->persist($audit);
        $this->entityManager->flush();

        return $audit;
    }

    public function getUnresolvedIssues(): array
    {
        return $this->auditRepository->findBy(['resolved' => false], ['createdAt' => 'DESC']);
    }

    public function resolveIssue(int $auditId): void
    {
        $audit = $this->auditRepository->find($auditId);
        
        if ($audit) {
            $audit->setResolved(true);
            $audit->setResolvedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }
    }
}