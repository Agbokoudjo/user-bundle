<?php

declare(strict_types=1);
/*
 * This file is part of the project by AGBOKOUDJO Franck.
 *
 * (c) AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */

namespace Wlindabla\UserBundle\UI\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\GenerateTemporaryPasswordHandler;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;

#[AsCommand(
    name: 'app:user:generate-temporary-password',
    description: 'Génère un mot de passe temporaire pour un utilisateur à partir de son email.',
)]
final class GenerateTemporaryPasswordCommand extends Command
{
    public function __construct(
        private readonly UserManagerInterface $userManager,
        private readonly GenerateTemporaryPasswordHandler $handler,
    ) {
        parent::__construct('app:user:generate-temporary-password');
    }

    protected function configure(): void
    {
        $this->addArgument(
            'email',
            InputArgument::REQUIRED,
            "L'adresse email de l'utilisateur."
        );
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim((string) $input->getArgument('email'));

        $io->title('Génération de mot de passe temporaire');

        // 1. Trouver l'utilisateur par email
        $user = $this->userManager->findUserByUsernameOrEmail($email);

        if ($user === null) {
            $io->error(sprintf('Aucun utilisateur trouvé avec l\'email "%s".', $email));
            return Command::FAILURE;
        }

        $io->text(sprintf('Utilisateur trouvé : <info>%s</info> (ID: %s)', $email, $user->getId()));

        // 2. Déléguer au handler (génération + mise à jour + dispatch d'événement)
        try {
            $this->handler->process($user->getId());
        } catch (\Throwable $e) {
            $io->error(sprintf('Erreur lors de la génération : %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Mot de passe temporaire généré et envoyé pour "%s".',
            $email
        ));

        return Command::SUCCESS;
    }
}
