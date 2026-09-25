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

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\MailingBundle\Mailing\MailerManagerInterface;
use Wlindabla\MailingBundle\Mailing\PriorityInterface;
use Wlindabla\UserBundle\Domain\User\Event\ToggleUserAccountEvent;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\CacheUserProviderInterface;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;

/**
 * Sends an email to notify a user their account was enabled or disabled.
 *
 * @internal called automatically by the EventDispatcher
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class ToggleUserAccountSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MailerManagerInterface $mailerManager,
        private readonly ParameterBagInterface $parameterBag,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly CacheUserProviderInterface $userProvider,
        private readonly TemplateRegistryInterface $templateRegistry,
        private readonly TranslatorInterface $translator,
        private readonly ?string $loginRouteName = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ToggleUserAccountEvent::class => 'onToggleUserAccountEvent',
        ];
    }

    public function onToggleUserAccountEvent(ToggleUserAccountEvent $event): void
    {
        $user = $event->getUser();

        try {
            $siteName = $this->parameterBag->get('app.site_name');
            $loginUrl = $this->generateLoginUrl();
            $isActive = $user->isEnabled();

            $subject = $this->translator->trans(
                $isActive ? 'toggle_account_email.subject_activated' : 'toggle_account_email.subject_deactivated',
                ['%site_name%' => $siteName],
                'WlindablaUserBundle',
            );

            $context = [
                'status' => $isActive,
                'username' => $user->getUsername(),
                'url_login' => $loginUrl,
                'site_name' => $siteName,
            ];

            if ($temporaryPassword = $event->getTemporaryPassword()) {
                $context['password_user'] = $temporaryPassword;
            }

            $this->mailerManager->notifyBySystem(
                $user->getEmail(),
                $subject,
                $this->templateRegistry->get('toggle_user_account'),
                $context,
                PriorityInterface::PRIORITY_NORMAL,
            );

            $this->userProvider->invalidateUserCache($user->getId());

            $this->logger->info('Account status change email sent.', [
                'user_email' => $user->getEmail(),
                'new_status' => $isActive ? 'enabled' : 'disabled',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send the account status change email.', [
                'user_email' => $user->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateLoginUrl(): ?string
    {
        if (null === $this->loginRouteName) {
            return null;
        }

        try {
            return $this->urlGenerator->generate($this->loginRouteName, [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException $e) {
            $this->logger->warning('Route configured for ToggleUserAccountSubscriber does not exist.', [
                'route' => $this->loginRouteName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}