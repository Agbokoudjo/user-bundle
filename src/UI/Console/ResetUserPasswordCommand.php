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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;

/**
 * Resets a SonataUser's password based on their email address.
 *
 * Usage: php bin/console app:user:reset-password
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsCommand(
    name: 'wlindabla:user:reset_password',
    description: 'Resets the password of a SonataUser using their email address.',
)]
final class ResetUserPasswordCommand extends Command
{
    private const int PASSWORD_MIN_LENGTH = 8;
    private const int PASSWORD_MAX_LENGTH = 80;

    public function __construct(
        private readonly UserManagerInterface $userManager,
    ) {
        parent::__construct('wlindabla:user:reset_password');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'User email address (prompted if omitted)')
            ->setHelp(
                <<<'HELP'
                This command searches for a SonataUser by email and assigns them a new password.

                  <info>php %command.full_name%</info>
                  <info>php %command.full_name% john.doe@example.com</info>
                HELP
            );
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Password Reset — SonataUser');

        $email = $input->getArgument('email') ?? $io->ask(
            'User email address',
            validator: $this->validateNotBlank(...),
        );

        $user = $this->userManager->findUserByEmail($email);

        if (!$user instanceof UserInterface) {
            $io->error(\sprintf('No user found for email "%s".', $email));

            return Command::FAILURE;
        }

        $io->text(\sprintf(
            'User found: <info>%s</info> (%s)',
            $user->getUsername() ?? '—',
            $user->getEmail() ?? '—',
        ));

        $plainPassword = $this->askForNewPassword($io);

        if (null === $plainPassword) {
            $io->warning('Operation cancelled: the entered passwords do not match.');

            return Command::FAILURE;
        }

        if (!$io->confirm(\sprintf('Confirm password reset for "%s"?', $user->getEmail()), false)) {
            $io->comment('Operation cancelled.');

            return Command::SUCCESS;
        }

        $user->setPlainPassword($plainPassword);
        $this->userManager->updatePassword($user);
        $this->userManager->save($user, true);

        $io->success(\sprintf('Password successfully updated for "%s".', $user->getEmail()));

        return Command::SUCCESS;
    }

    /**
     * Prompts for the new password (hidden input) and its confirmation.
     * Returns null if the two inputs do not match.
     */
    private function askForNewPassword(SymfonyStyle $io): ?string
    {
        $question = new Question('New password');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $question->setValidator($this->validatePasswordLength(...));

        $plainPassword = $io->askQuestion($question);

        $confirmQuestion = new Question('Confirm new password');
        $confirmQuestion->setHidden(true);
        $confirmQuestion->setHiddenFallback(false);

        $confirmation = $io->askQuestion($confirmQuestion);

        return $plainPassword === $confirmation ? $plainPassword : null;
    }

    private function validateNotBlank(?string $value): string
    {
        if (null === $value || '' === trim($value)) {
            throw new \RuntimeException('The email cannot be empty.');
        }

        return $value;
    }

    private function validatePasswordLength(?string $value): string
    {
        $length = \strlen((string) $value);

        if ($length < self::PASSWORD_MIN_LENGTH || $length > self::PASSWORD_MAX_LENGTH) {
            throw new \RuntimeException(\sprintf(
                'The password must contain between %d and %d characters.',
                self::PASSWORD_MIN_LENGTH,
                self::PASSWORD_MAX_LENGTH,
            ));
        }

        return $value;
    }
}