<?php

namespace App\Command;

use App\Repository\OrphelinatPhotoRepository;
use App\Repository\OrphelinatRepository;
use App\Repository\PropositionOrphelinatRepository;
use App\Service\FileUploader;
use App\Service\ImageOptimizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Génère les variantes WebP des images déjà en ligne. Les originaux sont conservés.
 */
#[AsCommand(name: 'app:images:optimiser', description: 'Génère les variantes WebP légères des photos existantes')]
class OptimiserImagesCommand extends Command
{
    public function __construct(
        private readonly ImageOptimizer $optimizer,
        private readonly FileUploader $uploader,
        private readonly OrphelinatRepository $orphelinatRepository,
        private readonly OrphelinatPhotoRepository $photoRepository,
        private readonly PropositionOrphelinatRepository $propositionRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('forcer', null, InputOption::VALUE_NONE, 'Regénérer les variantes existantes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fichiers = [];
        foreach ($this->orphelinatRepository->findAll() as $o) {
            $fichiers[] = $o->getImage();
        }
        foreach ($this->photoRepository->findAll() as $p) {
            $fichiers[] = $p->getFilename();
        }
        foreach ($this->propositionRepository->findAll() as $p) {
            $fichiers[] = $p->getImage();
        }
        $fichiers = array_unique(array_filter($fichiers));

        $total = 0;
        foreach ($io->progressIterate($fichiers) as $fichier) {
            $total += $this->optimizer->optimiser($fichier, (bool) $input->getOption('forcer'));
            // Copie en base (si FICHIERS_EN_BASE=1) de l'original et des variantes.
            $this->uploader->sauvegarder($fichier, FileUploader::IMAGE);
        }

        $io->success(sprintf('%d variante(s) créée(s) pour %d image(s).', $total, count($fichiers)));

        return Command::SUCCESS;
    }
}
