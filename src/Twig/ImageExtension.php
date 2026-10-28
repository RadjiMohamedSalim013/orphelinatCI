<?php

namespace App\Twig;

use App\Service\CoffreFichiers;
use App\Service\FileUploader;
use App\Service\ImageOptimizer;
use Symfony\Component\Asset\Packages;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * image_url(fichier, 640) : URL de la variante WebP si elle existe (sur le
 * disque ou dans la base), sinon de l'original.
 */
class ImageExtension extends AbstractExtension
{
    /** @var array<string, bool> */
    private array $existe = [];

    public function __construct(
        private readonly Packages $packages,
        private readonly CoffreFichiers $coffre,
        private readonly string $imagesDirectory,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('image_url', $this->url(...)),
        ];
    }

    public function url(?string $filename, ?int $taille = null): ?string
    {
        if (null === $filename || '' === $filename) {
            return null;
        }

        if (null !== $taille) {
            $variante = ImageOptimizer::variante($filename, $taille);
            $this->existe[$variante] ??= is_file($this->imagesDirectory.'/'.$variante)
                || $this->coffre->existe(CoffreFichiers::chemin(FileUploader::IMAGE, $variante));
            if ($this->existe[$variante]) {
                return $this->packages->getUrl('uploads/images/'.$variante);
            }
        }

        return $this->packages->getUrl('uploads/images/'.basename($filename));
    }
}
