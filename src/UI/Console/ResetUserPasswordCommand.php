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

namespace App\UI\Console;

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
 * Réinitialise le mot de passe d'un SonataUser à partir de son email.
 *
 * Usage : php bin/console app:user:reset-password
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsCommand(
    name: 'app:user:reset-password',
    description: 'Réinitialise le mot de passe d\'un utilisateur SonataUser à partir de son email.',
)]
final class ResetUserPasswordCommand extends Command
{
    private const int PASSWORD_MIN_LENGTH = 8;
    private const int PASSWORD_MAX_LENGTH = 80;

    public function __construct(
        private readonly UserManagerInterface $userManager,
    ) {
        parent::__construct('app:user:reset-password');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email de l\'utilisateur (demandé si omis)')
            ->setHelp(
                <<<'HELP'
                Cette commande recherche un SonataUser par email et lui
                attribue un nouveau mot de passe.

                  <info>php %command.full_name%</info>
                  <info>php %command.full_name% jean.dupont@example.com</info>
                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Réinitialisation du mot de passe — SonataUser');

        $email = $input->getArgument('email') ?? $io->ask(
            'Email de l\'utilisateur',
            validator: $this->validateNotBlank(...),
        );

        $user = $this->userManager->findUserByEmail($email);

        if (!$user instanceof UserInterface) {
            $io->error(\sprintf('Aucun utilisateur trouvé pour l\'email "%s".', $email));

            return Command::FAILURE;
        }

        $io->text(\sprintf(
            'Utilisateur trouvé : <info>%s</info> (%s)',
            $user->getUsername() ?? '—',
            $user->getEmail() ?? '—',
        ));

        $plainPassword = $this->askForNewPassword($io);

        if (null === $plainPassword) {
            $io->warning('Opération annulée : les deux mots de passe saisis ne correspondent pas.');

            return Command::FAILURE;
        }

        if (!$io->confirm(\sprintf('Confirmer la réinitialisation du mot de passe pour "%s" ?', $user->getEmail()), false)) {
            $io->comment('Opération annulée.');

            return Command::SUCCESS;
        }

        $user->setPlainPassword($plainPassword);
        $this->userManager->updatePassword($user);
        $this->userManager->save($user,true);

        $io->success(\sprintf('Mot de passe mis à jour pour "%s".', $user->getEmail()));

        return Command::SUCCESS;
    }

    /**
     * Demande le nouveau mot de passe (saisie masquée) puis sa confirmation.
     * Retourne null si les deux saisies ne correspondent pas.
     */
    private function askForNewPassword(SymfonyStyle $io): ?string
    {
        $question = new Question('Nouveau mot de passe');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $question->setValidator($this->validatePasswordLength(...));

        $plainPassword = $io->askQuestion($question);

        $confirmQuestion = new Question('Confirmer le nouveau mot de passe');
        $confirmQuestion->setHidden(true);
        $confirmQuestion->setHiddenFallback(false);

        $confirmation = $io->askQuestion($confirmQuestion);

        return $plainPassword === $confirmation ? $plainPassword : null;
    }

    private function validateNotBlank(?string $value): string
    {
        if (null === $value || '' === trim($value)) {
            throw new \RuntimeException('L\'email ne peut pas être vide.');
        }

        return $value;
    }

    private function validatePasswordLength(?string $value): string
    {
        $length = \strlen((string) $value);

        if ($length < self::PASSWORD_MIN_LENGTH || $length > self::PASSWORD_MAX_LENGTH) {
            throw new \RuntimeException(\sprintf(
                'Le mot de passe doit contenir entre %d et %d caractères.',
                self::PASSWORD_MIN_LENGTH,
                self::PASSWORD_MAX_LENGTH,
            ));
        }

        return $value;
    }
}
