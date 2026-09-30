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

namespace App\WlindablaUserBundle\Infrastructure\Persistance;

use Psr\Log\LoggerInterface;
use Wlindabla\MailingBundle\Mailing\MailerManagerInterface;
use Wlindabla\MailingBundle\Mailing\PriorityInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Model\TwoFactorCodeInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Service\TwoFactorCodeServiceInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;

/**
 * Orchestre le cycle de vie complet du code MFA envoyé par email 
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
final class TwoFactorCodeService implements TwoFactorCodeServiceInterface
{
    public function __construct(
        private readonly UserManagerInterface $userManager,
        private readonly MailerManagerInterface $mailerManager,
        private readonly LoggerInterface $logger
    ) {}

    public function sendCodeByEmail(
        string $userIdentifier, 
        string $plainCode, 
        string $context,
        string $siteName,
        ?string $ipAddress): void
    {
        $user = $this->userManager->findUserByUsernameOrEmail($userIdentifier);

        if ($user === null || $user->getEmail() === null) {
            $this->logger->error('Impossible d\'envoyer le code MFA : utilisateur ou email introuvable', [
                'user' => $userIdentifier,
            ]);
 
            return;
        }

        $this->mailerManager->notifyBySystem(
            $user->getEmail(),
            'Votre code de vérification — ' . $siteName,
            'email/security/twoFactorCode.html.twig',
            [
                'siteName'   => $siteName,
                'username'   => $user->getUsername(),
                'code'       => $plainCode,
                'ttlMinutes' => TwoFactorCodeInterface::DEFAULT_TTL_MINUTES,
                'ipAddress'  => $ipAddress,
                'attemptAt'  => new \DateTimeImmutable('now'),
                'context'    => $context,
            ],
            PriorityInterface::PRIORITY_HIGHEST,
        );

        $this->logger?->info('2FA requis : code envoyé, session marquée en attente', [
                'user' => $user->getUserIdentifier(),
            ]);
    }
}
