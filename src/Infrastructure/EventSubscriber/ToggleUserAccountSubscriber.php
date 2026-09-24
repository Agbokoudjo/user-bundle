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

use App\Infrastructure\Service\Mailing\MailerManagerInterface;
use App\Infrastructure\Service\Mailing\PriorityInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wlindabla\UserBundle\Domain\User\Event\ToggleUserAccountEvent;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\CacheUserProviderInterface;

/**
 * Subscriber pour gérer les notifications lors de l'activation/désactivation de comptes utilisateurs.
 * 
 * Envoie automatiquement un email à l'utilisateur pour l'informer du changement
 * de statut de son compte (activé ou désactivé).
 *
 * @internal Ce subscriber est appelé automatiquement par l'EventDispatcher
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Infrastructure\Listener
 */
final class ToggleUserAccountSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MailerManagerInterface $mailerManager,
        private readonly ParameterBagInterface $parameterBag,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private CacheUserProviderInterface $userProvider
       ) {}

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ToggleUserAccountEvent::class=> 'onToggleUserAccountEvent'
        ];
    }

    /**
     * Gère l'événement de changement de statut du compte utilisateur.
     *
     * Envoie un email de notification à l'utilisateur concerné.
     *
     * @param ToggleUserAccountEvent $event L'événement contenant les informations du compte
     * 
     * @return void
     */
    public function onToggleUserAccountEvent(ToggleUserAccountEvent $event): void
    {
        $user = $event->getUser();

        try {
            $siteName = $this->parameterBag->get('app.site_name');
            $loginUrl = $this->urlGenerator->generate(
                'app_admin_user_login',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $subject = $this->buildEmailSubject($user->getStatus(), $siteName);

            $context_twig=[
                    'status' => $user->getStatus(),
                    'username' => $user->getUsername(),
                    'url_login' => $loginUrl,
                    'NAME_SITE' => $siteName
                ];
           
            if($password_user = $event->getTemporaryPassword()){
                $context_twig['password_user']= $password_user; 
            }
            
            $this->mailerManager->notifyBySystem(
                $user->getEmail(),
                $subject,
                'email/profile/toggle_user_account.html.twig',
                $context_twig,
                PriorityInterface::PRIORITY_NORMAL
            );
             $this->userProvider->invalidateUserCache($user->getId()) ;
             
            $this->logger?->info('Email de changement de statut envoyé', [
                'user_email' => $user->getEmail(),
                'new_status' => $user->getStatus() ? 'activé' : 'désactivé'
            ]);
            
        } catch (\Exception $e) {
            $this->logger?->error('Échec de l\'envoi de l\'email de changement de statut', [
                'user_email' => $user->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Construit le sujet de l'email selon le statut du compte.
     *
     * @param bool $isActive Le statut du compte (true = activé, false = désactivé)
     * @param string $siteName Le nom du site
     * Instruction pour claude Ai: claude Ai devrait nous proposer de traduire les message en anglais c'est la version anglais  qui devrait etre envoyer chez l'utilisateur
     * les client messagerie moderne comme gmail dispose deja dans leur interface web des traducteur  de l'utilisateur 
     * @return string Le sujet de l'email
     */
    private function buildEmailSubject(bool $isActive, string $siteName): string
    {
        return $isActive
            ? sprintf('Votre compte %s a été activé', $siteName)
            : sprintf('Votre compte %s a été désactivé', $siteName);
    }
}