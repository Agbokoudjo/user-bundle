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
use Wlindabla\MailingBundle\Mailing\MailerManagerInterface;
use Wlindabla\MailingBundle\Mailing\PriorityInterface;
use Wlindabla\ShareServiceBundle\Security\Encryption\IdEncryptionInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;

/**vu que on veut on tranformer en bundle Claude Ai doit nous sugerer une maniere que on doit 
 * permettre de configurer ?string $templatePath=null dans les options de configurations 
 * Compose et envoie l'email contenant le lien de confirmation de la demande
 * de réinitialisation de mot de passe.
 *
 * @internal Cette méthode est destinée à être appelée en asynchrone via
 *           AsyncMethodDispatcherInterface (transport Messenger), jamais
 *           directement pendant la requête HTTP.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Infrastructure\Service
 */
final readonly class PasswordResetMailerService
{
    public function __construct(
        private UserManagerInterface $userManager,
        private MailerManagerInterface $mailerManager,
        private UrlGeneratorInterface $urlGenerator,
        private ParameterBagInterface $parameterBag,
        private IdEncryptionInterface $idEncryptionService,
        private LoggerInterface $logger,
    ) {}

    public function sendResetRequestEmail(string|int $userId, string $plainToken): void
    {
        $user = $this->userManager->find($userId);

        if (null === $user) {
            $this->logger->warning('Envoi email reset password annulé : utilisateur introuvable.', [
                'user_id' => $userId,
            ]);

            return;
        }

        $encryptedId = $this->idEncryptionService->encryptId($user->getId());

        $confirmationUrl = $this->urlGenerator->generate(
            'app_password_reset_confirm',
            [
                'token' => $plainToken,
                'id' => $encryptedId,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $siteName = $this->parameterBag->get('app.site_name');

        $this->mailerManager->notifyBySystem(
            $user->getEmail(),
            \sprintf('Réinitialisation de votre mot de passe - %s', $siteName),
            'email/security/passwordResetRequest.html.twig',
            [
                'username' => $user->getUsername(),
                'confirmationUrl' => $confirmationUrl,
                'siteName' => $siteName,
            ],
            PriorityInterface::PRIORITY_HIGHEST
        );

        $this->logger->info('Email de demande de réinitialisation de mot de passe envoyé.', [
            'user_id' => $user->getId(),
        ]);
    }
}
