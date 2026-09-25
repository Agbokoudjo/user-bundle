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

namespace Wlindabla\UserBundle\Infrastructure\EventSubscriber;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\MailingBundle\Mailing\MailerManagerInterface;
use Wlindabla\MailingBundle\Mailing\PriorityInterface;
use Wlindabla\ShareServiceBundle\Security\Encryption\IdEncryptionInterface;
use Wlindabla\UserBundle\Domain\User\Event\UserAccountEmailVerificationEvent;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;

/**
 * Sends the account-verification email when a UserAccountEmailVerificationEvent
 * is dispatched.
 *
 * @internal called automatically by the EventDispatcher
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final readonly class UserAccountEmailVerificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerManagerInterface $mailerManager,
        private UrlGeneratorInterface $router,
        private ParameterBagInterface $params,
        private IdEncryptionInterface $idEncryptionService,
        private TemplateRegistryInterface $templateRegistry,
        private TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserAccountEmailVerificationEvent::class => 'onUserAccountCreated',
        ];
    }

    public function onUserAccountCreated(UserAccountEmailVerificationEvent $event): void
    {
        $encryptedId = $this->idEncryptionService->encryptId($event->getUserId());

        $confirmationUrl = $this->router->generate(
            'app.verify.email',
            ['token' => $event->getRawToken(), 'id' => $encryptedId],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $siteName = $this->params->get('app.site_name');

        $subject = $this->translator->trans(
            'verification_email.subject',
            ['%site_name%' => $siteName],
            'WlindablaUserBundle',
        );

        $this->mailerManager->notifyBySystem(
            $event->getEmail(),
            $subject,
            $this->templateRegistry->get('email_verification'),
            [
                'recipientEmail' => $event->getEmail(),
                'username' => $event->getUsername(),
                'confirmationUrl' => $confirmationUrl,
                'siteName' => $siteName,
            ],
            PriorityInterface::PRIORITY_HIGH,
        );
    }
}