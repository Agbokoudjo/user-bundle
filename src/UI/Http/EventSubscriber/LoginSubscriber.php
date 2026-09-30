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

namespace Wlindabla\UserBundle\UI\Http\EventSubscriber;

use App\Infrastructure\Doctrine\Entity\LoggerLoginUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\ShareServiceBundle\Security\Browser\DeviceFingerprintUserAgent;
use Wlindabla\ShareServiceBundle\Security\Browser\UserAgentParserInterface;
use Wlindabla\UserBundle\Domain\LoggerLoginUser\Application\Manager\LoggerLoginUserManagerInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Application\Manager\TwoFactorCodeManagerInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Enum\TwoFactorAuthKey;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Subscriber pour l'enregistrement des tentatives de connexion réussies.
 *
 * Responsabilités :
 * - Logger les connexions réussies (LoggerLoginUser) de manière asynchrone
 * - Poser un flag "session en attente d'enregistrement" en session PHP,
 *   consommé par SessionControlListener à la requête SUIVANTE (celle-ci
 *   verra le sessionId FINAL, après régénération par la stratégie de
 *   fixation de session de Symfony Security).
 *
 * @internal Ce subscriber est appelé automatiquement par Symfony Security
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Infrastructure\EventSubscriber
 */
final class LoginSubscriber implements EventSubscriberInterface
{
     use DeviceFingerprintUserAgent;

    /**
     * Clé de session utilisée pour transmettre les infos de connexion à
     * SessionControlListener, qui les consomme à la requête suivante
     * (une seule fois) pour créer l'enregistrement UserSession définitif.
     */
    public const string PENDING_SESSION_REGISTRATION = '_pending_session_registration';

    public function __construct(
        private readonly AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        private readonly UserAgentParserInterface $userAgentParser,
        private readonly ?LoggerInterface $logger = null,
    ) {}

     public static function getSubscribedEvents(): array
    {
        return [
            // Connexion interactive (formulaire classique, HTTP Basic…)
            SecurityEvents::INTERACTIVE_LOGIN => [
                ['onLastLoginUpdate', 0],
                // Doit s'exécuter AVANT onMarkPendingSession : si le second
                // facteur est requis, la session ne doit pas encore être
                // considérée comme "en attente d'enregistrement" définitif
                // (TwoFactorAuthListener bloquera de toute façon l'accès
                // aux routes sonata_* tant que PENDING_2FA est présent).
                ['onRequireTwoFactor', 10],
                ['onMarkPendingSession', 0],
            ],
        ];
    }

    /**
     * Mot de passe correct → déclenche l'envoi du code MFA (13 chiffres +
     * 7 lettres, par email) et marque la session PHP courante comme
     * "2FA en attente". TwoFactorAuthListener bloque alors toute route
     * sonata_* jusqu'à validation du code sur /admin/2fa.
     */
    public function onRequireTwoFactor(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof BaseUserInterface) {
            return;
        }

        $request = $event->getRequest();

        // Le flag est posé AVANT l'envoi, volontairement : si l'email
        // échoue mais que le code a été persisté (generateAndSend flush
        // en base avant de tenter l'envoi), TwoFactorAuthListener doit
        // quand même bloquer l'accès. On préfère fermer l'accès (fail
        // closed) plutôt que de laisser passer un login sans second
        // facteur à cause d'un incident SMTP.
        $request->getSession()->set(TwoFactorAuthKey::PENDING_2FA->value, true);

        try {
            $this->asyncMethodDispatcher->dispatch(
                TwoFactorCodeManagerInterface::class,
                'generateAndSend',
                [ $user->getUserIdentifier(),$request->getClientIp(),'login',]
            );
        } catch (\Throwable $e) {
            $this->logger?->critical('Échec de la génération/envoi du code MFA à la connexion', [
                'user'  => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }
    }

      /**
     * Pose le flag de session en attente d'enregistrement.
     *
     * Ne crée PAS la UserSession ici : à ce stade du cycle de vie, le
     * sessionId n'est pas encore le sessionId final (la stratégie de
     * fixation de session le régénère juste après l'authentification).
     * SessionControlListener finalisera l'enregistrement sur la requête
     * suivante, avec le sessionId définitif.
     */
    public function onMarkPendingSession(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof BaseUserInterface) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        $session->set(self::PENDING_SESSION_REGISTRATION, [
            'userIdentifier'    => $user->getUserIdentifier(),
            'ipAddress'         => $request->getClientIp(),
            'userAgent'         => $request->headers->get('User-Agent'),
            'deviceFingerprint' => $this->buildDeviceFingerprint($request),
        ]);
    }

    /**
     * Enregistre un log de connexion réussie (username, email, IP, createdAt auto).
     */
    public function onLastLoginUpdate(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof BaseUserInterface) {
            $this->logger?->debug('Utilisateur non valide pour la journalisation de connexion', [
                'user_class' => $user ? $user::class : 'null',
            ]);

            return;
        }

        try {
            $rawUserAgent = $event->getRequest()->headers->get('User-Agent');
            $userAgentLabel = $this->userAgentParser->parse($rawUserAgent) ;
        
            $this->asyncMethodDispatcher->dispatch(
                LoggerLoginUserManagerInterface::class,
                'createAndSave',
                [$user->getUsername(),
                 $user->getEmail(),
                 $event->getRequest()->getClientIp(),
                 $rawUserAgent,
                 $userAgentLabel,
                 new \DateTimeImmutable("now", new \DateTimeZone("UTC"))]
            );

            $this->logger?->info('Journalisation de connexion dispatchée', [
                'user_id' => $user->getId(),
                'ip'      => $event->getRequest()->getClientIp(),
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Échec de la persistance du journal de connexion.', [
                'exception' => $e->getMessage(),
                'username'  => $user->getUsername(),
            ]);
        }
    }
}