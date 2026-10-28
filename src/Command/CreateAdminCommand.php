<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un administrateur ou réinitialise ses identifiants'
)]
class CreateAdminCommand extends Command
{
    private const MIN_LENGTH = 12;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        // Les identifiants ne sont jamais écrits dans le code (le dépôt est public) :
        // variables d'environnement ADMIN_EMAIL / ADMIN_PASSWORD, ou saisie interactive.
        $email = $this->env('ADMIN_EMAIL') ?? (string) $io->ask('E-mail de l\'administrateur');
        $email = mb_strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Adresse e-mail invalide.');

            return Command::INVALID;
        }

        $password = $this->env('ADMIN_PASSWORD') ?? (string) $io->askHidden(sprintf('Mot de passe (%d caractères minimum)', self::MIN_LENGTH));

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $io->error(sprintf('Le mot de passe doit contenir au moins %d caractères.', self::MIN_LENGTH));

            return Command::INVALID;
        }

        $user = $this->userRepository->findOneBy([
            'email' => $email,
        ]);

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
        }

        // Attribuer le rôle administrateur.
        $user->setRoles(['ROLE_ADMIN']);

        // Hacher le mot de passe avant de l'enregistrer.
        $user->setPassword(
            $this->passwordHasher->hashPassword(
                $user,
                $password
            )
        );

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Administrateur %s créé ou mis à jour avec succès.',
            $email
        ));

        return Command::SUCCESS;
    }

    private function env(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return is_string($value) && '' !== $value ? $value : null;
    }
}
