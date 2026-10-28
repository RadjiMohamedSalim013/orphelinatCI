<?php

namespace App\Command;

use App\Entity\Commune;
use App\Entity\Pays;
use App\Entity\Ville;
use App\Repository\PaysRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Initialise le référentiel géographique de la Côte d'Ivoire : les communes du
 * district d'Abidjan et les principales villes (chacune érigée en commune).
 *
 * Idempotente : les éléments existants sont reconnus sans tenir compte des
 * accents, de la casse ni des tirets, et ne sont jamais modifiés ni dupliqués.
 */
#[AsCommand(
    name: 'app:referentiel:cote-divoire',
    description: 'Ajoute les villes et communes de Côte d\'Ivoire manquantes au référentiel'
)]
class ImportReferentielCommand extends Command
{
    private const PAYS = 'Côte d’Ivoire';

    /** Ville => communes. Hors Abidjan, la ville constitue une commune. */
    private const VILLES = [
        'Abidjan' => ['Abobo', 'Adjamé', 'Anyama', 'Attécoubé', 'Bingerville', 'Cocody', 'Koumassi', 'Marcory', 'Plateau', 'Port-Bouët', 'Songon', 'Treichville', 'Yopougon'],
        'Yamoussoukro' => ['Yamoussoukro'],
        'Bouaké' => ['Bouaké'],
        'Daloa' => ['Daloa'],
        'San-Pédro' => ['San-Pédro'],
        'Korhogo' => ['Korhogo'],
        'Man' => ['Man'],
        'Gagnoa' => ['Gagnoa'],
        'Divo' => ['Divo'],
        'Abengourou' => ['Abengourou'],
        'Soubré' => ['Soubré'],
        'Bondoukou' => ['Bondoukou'],
        'Odienné' => ['Odienné'],
        'Séguéla' => ['Séguéla'],
        'Grand-Bassam' => ['Grand-Bassam'],
        'Dabou' => ['Dabou'],
        'Agboville' => ['Agboville'],
        'Aboisso' => ['Aboisso'],
        'Dimbokro' => ['Dimbokro'],
        'Ferkessédougou' => ['Ferkessédougou'],
        'Katiola' => ['Katiola'],
    ];

    private AsciiSlugger $slugger;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaysRepository $paysRepository,
    ) {
        parent::__construct();
        $this->slugger = new AsciiSlugger('fr');
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Afficher les ajouts sans les enregistrer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $ajouts = [];

        $pays = $this->find($this->paysRepository->findAll(), self::PAYS);
        if (null === $pays) {
            $pays = (new Pays())->setNom(self::PAYS);
            $this->entityManager->persist($pays);
            $ajouts[] = ['Pays', self::PAYS];
        }

        foreach (self::VILLES as $nomVille => $communes) {
            $ville = $this->find($pays->getVilles(), $nomVille);
            if (null === $ville) {
                $ville = (new Ville())->setNom($nomVille);
                $pays->addVille($ville);
                $this->entityManager->persist($ville);
                $ajouts[] = ['Ville', $nomVille];
            }

            foreach ($communes as $nomCommune) {
                if (null === $this->find($ville->getCommunes(), $nomCommune)) {
                    $commune = (new Commune())->setNom($nomCommune);
                    $ville->addCommune($commune);
                    $this->entityManager->persist($commune);
                    $ajouts[] = ['Commune', $nomCommune.' ('.$nomVille.')'];
                }
            }
        }

        if (!$ajouts) {
            $io->success('Le référentiel est déjà complet.');

            return Command::SUCCESS;
        }

        $io->table(['Type', 'Nom'], $ajouts);

        if ($input->getOption('dry-run')) {
            $io->note(sprintf('%d ajout(s) simulé(s), rien n\'a été enregistré.', count($ajouts)));

            return Command::SUCCESS;
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d élément(s) ajouté(s).', count($ajouts)));

        return Command::SUCCESS;
    }

    /**
     * @template T of Pays|Ville|Commune
     *
     * @param iterable<T> $elements
     *
     * @return T|null
     */
    private function find(iterable $elements, string $nom): ?object
    {
        $cle = $this->key($nom);
        foreach ($elements as $element) {
            if ($this->key((string) $element->getNom()) === $cle) {
                return $element;
            }
        }

        return null;
    }

    private function key(string $nom): string
    {
        return $this->slugger->slug(str_replace(['’', '\''], ' ', $nom))->lower()->toString();
    }
}
