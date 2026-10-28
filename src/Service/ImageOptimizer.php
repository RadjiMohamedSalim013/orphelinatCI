<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Génère des variantes WebP redimensionnées d'une image envoyée :
 * « photo.png » donne « photo-640.webp » (cartes) et « photo-1600.webp » (fiche).
 * L'original n'est jamais modifié ; sans variante, l'original reste utilisé.
 */
class ImageOptimizer
{
    public const TAILLES = [640, 1600];
    private const QUALITE = 78;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $imagesDirectory,
    ) {
    }

    public static function variante(string $filename, int $taille): string
    {
        return pathinfo($filename, PATHINFO_FILENAME).'-'.$taille.'.webp';
    }

    /**
     * @return int nombre de variantes créées
     */
    public function optimiser(string $filename, bool $forcer = false): int
    {
        $source = $this->imagesDirectory.'/'.basename($filename);
        if (!is_file($source) || !function_exists('imagewebp')) {
            return 0;
        }

        $image = $this->charger($source);
        if (null === $image) {
            $this->logger->warning('Image illisible, variantes non générées.', ['fichier' => $filename]);

            return 0;
        }

        $crees = 0;
        $largeur = imagesx($image);
        $hauteur = imagesy($image);

        foreach (self::TAILLES as $taille) {
            $cible = $this->imagesDirectory.'/'.self::variante($filename, $taille);
            if (!$forcer && is_file($cible)) {
                continue;
            }

            $ratio = min(1, $taille / max($largeur, 1));
            $l = max(1, (int) round($largeur * $ratio));
            $h = max(1, (int) round($hauteur * $ratio));

            $copie = imagecreatetruecolor($l, $h);
            imagealphablending($copie, false);
            imagesavealpha($copie, true);
            imagecopyresampled($copie, $image, 0, 0, 0, 0, $l, $h, $largeur, $hauteur);

            if (imagewebp($copie, $cible, self::QUALITE)) {
                ++$crees;
            }
            imagedestroy($copie);
        }

        imagedestroy($image);

        return $crees;
    }

    private function charger(string $chemin): ?\GdImage
    {
        $type = @getimagesize($chemin)[2] ?? null;
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($chemin),
            IMAGETYPE_PNG => @imagecreatefrompng($chemin),
            IMAGETYPE_WEBP => @imagecreatefromwebp($chemin),
            default => false,
        };

        if (!$image instanceof \GdImage) {
            return null;
        }

        // Respecter l'orientation des photos prises au téléphone.
        if (function_exists('exif_read_data') && IMAGETYPE_JPEG === $type) {
            $orientation = @exif_read_data($chemin)['Orientation'] ?? 1;
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if (0 !== $angle) {
                $image = imagerotate($image, $angle, 0) ?: $image;
            }
        }

        return $image;
    }
}
