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
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\MailingBundle\Mailing\MailerManagerInterface;
use Wlindabla\MailingBundle\Mailing\PriorityInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Model\TwoFactorCodeInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Service\TwoFactorCodeServiceInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class TwoFactorCodeService implements TwoFactorCodeServiceInterface
{
    public function __construct(
        private readonly UserManagerInterface $userManager,
        private readonly MailerManagerInterface $mailerManager,
        private readonly TemplateRegistryInterface $templateRegistry,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendCodeByEmail(
        string $userIdentifier,
        string $plainCode,
        string $context,
        string $siteName,
        ?string $ipAddress,
    ): void {
        $user = $this->userManager->findUserByUsernameOrEmail($userIdentifier);
        if (null === $user || null === $user->getEmail()) {
            $this->logger->critical('Cannot send the MFA code: user or email not found — likely data integrity issue.', [
                'user' => $userIdentifier,
            ]);

            return;
        }

        $this->mailerManager->notifyBySystem(
            $user->getEmail(),
            $this->translator->trans('two_factor_email.subject', ['%site_name%' => $siteName], 'WlindablaUserBundle'),
            $this->templateRegistry->get('two_factor_code_email'),
            [
                'siteName' => $siteName,
                'username' => $user->getUsername(),
                'code' => $plainCode,
                'ttlMinutes' => TwoFactorCodeInterface::DEFAULT_TTL_MINUTES,
                'ipAddress' => $ipAddress,
                'attemptAt' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                'context' => $context,
            ],
            PriorityInterface::PRIORITY_HIGHEST,
        );

        $this->logger->info('2FA required: code sent.', ['user' => $user->getUserIdentifier()]);
    }
}