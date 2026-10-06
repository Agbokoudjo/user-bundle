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
    description: 'Generates a temporary password for a user based on their email address.',
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
            "The user's email address."
        );
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim((string) $input->getArgument('email'));

        $io->title('Temporary password generation');

        // 1. Trouver l'utilisateur par email
        $user = $this->userManager->findUserByUsernameOrEmail($email);

        if ($user === null) {
            $io->error(sprintf('No user found with the email "%s".', $email));
            return Command::FAILURE;
        }

        $io->text(sprintf('User found : <info>%s</info> (ID: %s)', $email, $user->getId()));

        // 2. Déléguer au handler (génération + mise à jour + dispatch d'événement)
        try {
            $this->handler->process($user->getId());
        } catch (\Throwable $e) {
            $io->error(sprintf('Error during generation : %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Temporary password generated and sent for "%s".',
            $email
        ));

        return Command::SUCCESS;
    }
}
