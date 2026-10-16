<?php

declare(strict_types=1);

/*
 * This file is part of the project by AGBOKOUDJO Franck.
 *
 * (c) AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * Phone: +229 01 67 25 18 86
 * LinkedIn: https://www.linkedin.com/in/internationales-web-apps-services-120520193/
 * Github: https://github.com/Agbokoudjo/
 * Company: INTERNATIONALES WEB APPS & SERVICES
 *
 * For more information, please feel free to contact the author.
 */

namespace Wlindabla\UserBundle\UI\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wlindabla\UserBundle\Domain\UserSession\Application\Manager\UserSessionManagerInterface;
use Wlindabla\UserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsCommand(
    name: 'wlindabla:user-session:revoke',
    description: 'Revokes all active sessions for a single user, identified by their userIdentifier.',
)]
final class RevokeUserSessionCommand extends Command
{
    public function __construct(
        private readonly UserSessionManagerInterface $sessionManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'userIdentifier',
            InputArgument::REQUIRED,
            'The user identifier (e.g. email or username) whose sessions must be revoked.',
        );
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userIdentifier = (string) $input->getArgument('userIdentifier');

        if (!$io->confirm(\sprintf('Revoke all active sessions for "%s"? They will be logged out everywhere.', $userIdentifier), false)) {
            $io->comment('Aborted.');

            return Command::SUCCESS;
        }

        $this->sessionManager->revokeAllActiveSessions($userIdentifier, UserSessionRevokedReason::MANUAL_LOGOUT);

        $this->logger?->info('Sessions revoked via console command.', ['user' => $userIdentifier]);

        $io->success(\sprintf('All active sessions for "%s" have been revoked.', $userIdentifier));

        return Command::SUCCESS;
    }
}