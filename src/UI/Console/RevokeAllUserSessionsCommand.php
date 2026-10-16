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
    name: 'wlindabla:user-session:revoke-all',
    description: 'Revokes every active session, for every user — a global logout.',
)]
final class RevokeAllUserSessionsCommand extends Command
{
    public function __construct(
        private readonly UserSessionManagerInterface $sessionManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
        parent::__construct();
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$io->confirm('This will revoke EVERY active session, for EVERY user. Continue?', false)) {
            $io->comment('Aborted.');

            return Command::SUCCESS;
        }

        $revokedCount = $this->sessionManager->revokeEverySession(UserSessionRevokedReason::MANUAL_LOGOUT);

        $this->logger?->warning('All sessions revoked via console command.', ['count' => $revokedCount]);

        $io->success(\sprintf('%d session(s) revoked.', $revokedCount));

        return Command::SUCCESS;
    }
}