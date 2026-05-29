<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un administrateur pour OrphaMap (test)'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Vérifier si admin existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@orphamap.com']);
        if ($existingUser) {
            $io->warning('Admin existe déjà : ' . $existingUser->getEmail());
            return Command::SUCCESS;
        }

        $user = new User();
        $user->setEmail('admin@orphamap.com');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'admin123'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success([
            'Admin créé avec succès !',
            'Email: admin@orphamap.com',
            'Mot de passe: admin123',
            'Login: /login → Dashboard /admin'
        ]);

        return Command::SUCCESS;
    }
}
