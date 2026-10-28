<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Centralise l'enregistrement des fichiers envoyés.
 *
 * Les formulaires valident déjà type et taille ; le contrôle est refait ici à
 * partir du contenu réel du fichier (pas de son nom), par défense en profondeur.
 */
class FileUploader
{
    public const IMAGE = 'image';
    public const DOCUMENT = 'document';
    public const PRIVATE = 'private';

    private const ALLOWED = [
        self::IMAGE => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
        self::DOCUMENT => ['application/pdf' => 'pdf'],
        self::PRIVATE => ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
    ];

    private Filesystem $filesystem;

    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly LoggerInterface $logger,
        private readonly ImageOptimizer $imageOptimizer,
        private readonly CoffreFichiers $coffre,
        private readonly string $imagesDirectory,
        private readonly string $documentsDirectory,
        private readonly string $privateDirectory,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * @throws UploadException lorsque le fichier est refusé ou ne peut être enregistré
     */
    public function upload(UploadedFile $file, string $kind): string
    {
        $allowed = self::ALLOWED[$kind] ?? throw new \InvalidArgumentException(sprintf('Type de fichier inconnu "%s".', $kind));

        $mime = $file->getMimeType();
        if (!isset($allowed[$mime])) {
            throw new UploadException('Type de fichier non autorisé.');
        }

        $base = $this->slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))->lower()->truncate(60)->toString();
        $filename = sprintf('%s-%s.%s', '' !== $base ? $base : 'fichier', bin2hex(random_bytes(6)), $allowed[$mime]);

        try {
            $file->move($this->directory($kind), $filename);
        } catch (FileException $e) {
            $this->logger->error('Échec de l\'enregistrement d\'un fichier envoyé.', ['exception' => $e]);

            throw new UploadException('Le fichier n\'a pas pu être enregistré. Réessayez plus tard.', previous: $e);
        }

        if (self::IMAGE === $kind) {
            $this->imageOptimizer->optimiser($filename);
        }

        $this->sauvegarder($filename, $kind);

        return $filename;
    }

    /**
     * Copie le fichier (et ses variantes) dans la base si FICHIERS_EN_BASE est activé.
     */
    public function sauvegarder(string $filename, string $kind): void
    {
        foreach ($this->fichiersAssocies($filename, $kind) as $nom) {
            $this->coffre->sauver(CoffreFichiers::chemin($kind, $nom), $this->directory($kind).'/'.$nom);
        }
    }

    public function delete(?string $filename, string $kind): void
    {
        if (null === $filename || '' === $filename) {
            return;
        }

        foreach ($this->fichiersAssocies($filename, $kind) as $nom) {
            $this->filesystem->remove($this->directory($kind).'/'.$nom);
            $this->coffre->supprimer(CoffreFichiers::chemin($kind, $nom));
        }
    }

    /**
     * Chemin absolu d'un fichier existant (restauré depuis la base si besoin),
     * ou null. Le nom est réduit à son dernier segment pour empêcher toute
     * remontée d'arborescence.
     */
    public function path(string $filename, string $kind): ?string
    {
        $nom = basename(str_replace('\\', '/', $filename));
        $path = $this->directory($kind).'/'.$nom;

        return $this->coffre->restaurer(CoffreFichiers::chemin($kind, $nom), $path) ? $path : null;
    }

    /**
     * Restaure un fichier public manquant sur le disque (photos, documents officiels).
     */
    public function restaurerPublic(string $dossier, string $filename): ?string
    {
        $kind = match ($dossier) {
            'images' => self::IMAGE,
            'documents' => self::DOCUMENT,
            default => null,
        };

        return null === $kind ? null : $this->path($filename, $kind);
    }

    /**
     * @return list<string> le fichier et, pour une image, ses variantes optimisées
     */
    private function fichiersAssocies(string $filename, string $kind): array
    {
        $nom = basename(str_replace('\\', '/', $filename));
        if (self::IMAGE !== $kind) {
            return [$nom];
        }

        return [$nom, ...array_map(static fn (int $t) => ImageOptimizer::variante($nom, $t), ImageOptimizer::TAILLES)];
    }

    private function directory(string $kind): string
    {
        return match ($kind) {
            self::IMAGE => $this->imagesDirectory,
            self::DOCUMENT => $this->documentsDirectory,
            self::PRIVATE => $this->privateDirectory,
        };
    }
}
