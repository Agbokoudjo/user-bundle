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

namespace Wlindabla\UserBundle\Infrastructure\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\MailingBundle\Mailing\MailerManagerInterface;
use Wlindabla\MailingBundle\Mailing\PriorityInterface;
use Wlindabla\ShareServiceBundle\Security\Encryption\IdEncryptionInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;
use Wlindabla\UserBundle\UI\Http\Controller\PasswordResetConfirmationController;

/**
 * Composes and sends the password-reset confirmation email.
 *
 * @internal meant to be called asynchronously via
 *           AsyncMethodDispatcherInterface (Messenger transport), never
 *           directly during the HTTP request.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final readonly class PasswordResetMailerService
{
    public function __construct(
        private UserManagerInterface $userManager,
        private MailerManagerInterface $mailerManager,
        private UrlGeneratorInterface $urlGenerator,
        private ParameterBagInterface $parameterBag,
        private IdEncryptionInterface $idEncryptionService,
        private TemplateRegistryInterface $templateRegistry,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    public function sendResetRequestEmail(string|int $userId, string $plainToken): void
    {
        $user = $this->userManager->find($userId);

        if (null === $user) {
            $this->logger->warning('Password reset email not sent: user not found.', ['user_id' => $userId]);

            return;
        }

        $confirmationUrl = $this->urlGenerator->generate(
            PasswordResetConfirmationController::ROUTE_NAME,
            ['token' => $plainToken, 'id' => $this->idEncryptionService->encryptId($user->getId())],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $siteName = $this->parameterBag->get('app.site_name');

        $this->mailerManager->notifyBySystem(
            $user->getEmail(),
            $this->translator->trans('password_reset_email.subject', ['%site_name%' => $siteName], 'WlindablaUserBundle'),
            $this->templateRegistry->get('password_reset_email'),
            [
                'username' => $user->getUsername(),
                'confirmationUrl' => $confirmationUrl,
                'site_name' => $siteName,
            ],
            PriorityInterface::PRIORITY_HIGHEST,
        );

        $this->logger->info('Password reset request email sent.', ['user_id' => $user->getId()]);
    }
}