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

namespace App\UI\Http\EventSubscriber;

use App\Domain\TwoFactorAuth\Application\Persistance\TwoFactorCodeManagerInterface;
use App\Domain\User\Model\BaseUserInterface;
use App\Infrastructure\Doctrine\Entity\SonataUser;
use App\Infrastructure\Persistance\UserManagerInterface;
use App\Infrastructure\Queue\AsyncMethodDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Gardien du second facteur — bloque l'accès aux routes Sonata tant que
 * l'utilisateur authentifié n'a pas validé son code MFA.
 *
 * Priorité 7, AVANT SessionControlListener (repriorisé à 6) : tant que le
 * code n'est pas validé, aucune UserSession (session unique) ne doit être
 * créée pour ce navigateur — c'est ce qui permet à la finalisation de
 * SessionControlListener::onMarkPendingSession de ne se produire qu'après
 * la redirection vers le tableau de bord, une fois le 2FA passé.
 *
 * Piège évité : la route de l'écran de code elle-même est exclue du
 * contrôle, sous peine de boucle de redirection infinie.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class TwoFactorAuthListener
{
    /**
     * Clé de session : présence = 2FA en attente de validation pour cette session PHP.
     */
    public const string PENDING_2FA = '_2fa_pending';

    /**
     * Fenêtre d'inactivité glissante — au-delà, la fraîcheur MFA est
     * considérée périmée même si le plafond absolu n'est pas atteint.
     * Mesurée entre SonataUser::$twoFactorLastCheckedAt et maintenant,
     * PAS depuis le dernier login : une session active en continu ne
     * redemande jamais de code.
     */
    private const int INACTIVITY_THRESHOLD_SECONDS = 2 * 3600; // 2h

    /**
     * Plafond absolu depuis SonataUser::$twoFactorValidatedAt — s'applique
     * même en cas d'activité continue ininterrompue, pour border le risque
     * dans le temps quoi qu'il arrive.
     */
    private const int ABSOLUTE_CAP_SECONDS = 24 * 3600; // 24h

    /**
     * Throttle d'écriture de $twoFactorLastCheckedAt : pas une mise à jour
     * en base à CHAQUE requête (coûteux en mode worker FrankenPHP à fort
     * trafic), une seule au plus toutes les 5 minutes suffit à la
     * précision recherchée pour la fenêtre d'inactivité de 2h ci-dessus.
     */
    private const int ACTIVITY_TOUCH_THROTTLE_SECONDS = 5 * 60; // 5 min

    /**
     * Routes accessibles à un utilisateur authentifié mais dont le 2FA
     * est encore en attente (écran de code, renvoi, déconnexion).
     */
    private const array ALLOWED_ROUTES_WHILE_PENDING = [
        'app_two_factor_show',
        'app_two_factor_verify',
        'app_two_factor_resend',
        'sonata_user_admin_security_logout',
        'app_admin_user_login',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RouterInterface $router,
        private TwoFactorCodeManagerInterface $twoFactorCodeManager,
        private AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        private UserManagerInterface $userManager,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route   = $request->attributes->get('_route');

        if (\in_array($route, self::ALLOWED_ROUTES_WHILE_PENDING, true)) {
            return;
        }

        // Seules les routes admin (Sonata + login JSON) sont concernées.
        if (!str_starts_with($route ?? '', 'sonata_') && $route !== 'app_admin_user_login') {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof BaseUserInterface) {
            return;
        }

        $session = $request->getSession();

        if ($session->get(self::PENDING_2FA, false)) {
            // Le flag peut être périmé (code déjà validé/expiré côté BDD) —
            // on retire le flag de session s'il n'y a plus rien à valider.
            if ($this->twoFactorCodeManager->findActivePending($user->getUserIdentifier()) === null) {
                $session->remove(self::PENDING_2FA);

                return;
            }

            $this->logger->info('Accès bloqué : 2FA en attente de validation', [
                'user'  => $user->getUserIdentifier(),
                'route' => $route,
            ]);

            $event->setResponse($this->buildRedirectResponse($request));
            $event->stopPropagation();

            return;
        }

        // Pas de flag en session : soit déjà validé plus tôt dans cette
        // même session PHP (cas normal), soit — c'est le trou qu'on ferme
        // ici — authentifié SILENCIEUSEMENT par remember-me, qui ne
        // déclenche jamais INTERACTIVE_LOGIN et donc ne pose jamais ce
        // flag. Dans les deux cas, on retombe désormais sur la fraîcheur
        // persistée sur le compte lui-même (SonataUser::$twoFactorValidatedAt),
        // qui elle survit à un changement de session PHP.
        if (!$user instanceof SonataUser) {
            $this->logger->warning('Type utilisateur inattendu pour la vérification de fraîcheur MFA — accès refusé par prudence (fail closed).', [
                'user' => $user->getUserIdentifier(),
            ]);

            $event->setResponse($this->buildRedirectResponse($request));
            $event->stopPropagation();

            return;
        }

        if (!$this->isTwoFactorFresh($user)) {
            $this->logger->info('Accès bloqué : fraîcheur MFA expirée (inactivité > 2h ou plafond de 24h dépassé).', [
                'user'  => $user->getUserIdentifier(),
                'route' => $route,
            ]);

            $this->forceChallenge($request, $user);

            $event->setResponse($this->buildRedirectResponse($request));
            $event->stopPropagation();

            return;
        }

        $this->touchTwoFactorActivity($user);
    }

    /**
     * La fraîcheur MFA de $user est-elle encore valide, selon les deux
     * règles indépendantes (la première qui échoue invalide la fraîcheur) :
     * - fenêtre d'inactivité glissante (INACTIVITY_THRESHOLD_SECONDS) ;
     * - plafond absolu depuis la dernière validation (ABSOLUTE_CAP_SECONDS).
     *
     * Absence de l'une ou l'autre date (jamais validé, ou compte créé
     * avant l'introduction de ce mécanisme) → toujours considéré périmé,
     * fail closed.
     */
    private function isTwoFactorFresh(SonataUser $user): bool
    {
        $validatedAt = $user->getTwoFactorValidatedAt();
        $lastCheckedAt = $user->getTwoFactorLastCheckedAt();

        if (null === $validatedAt || null === $lastCheckedAt) {
            return false;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $inactivitySeconds = $now->getTimestamp() - $lastCheckedAt->getTimestamp();
        if ($inactivitySeconds > self::INACTIVITY_THRESHOLD_SECONDS) {
            return false;
        }

        $totalElapsedSeconds = $now->getTimestamp() - $validatedAt->getTimestamp();

        return $totalElapsedSeconds <= self::ABSOLUTE_CAP_SECONDS;
    }

    /**
     * Déclenche un nouveau défi MFA — même mécanisme que
     * LoginSubscriber::onRequireTwoFactor(), mais appelable ici pour le cas
     * remember-me qui ne passe jamais par INTERACTIVE_LOGIN. Le flag est
     * posé AVANT l'envoi du code, pour les mêmes raisons de fail-closed que
     * dans LoginSubscriber (cf. son docblock).
     */
    private function forceChallenge(Request $request, SonataUser $user): void
    {
        $request->getSession()->set(self::PENDING_2FA, true);

        try {
            $this->asyncMethodDispatcher->dispatch(
                TwoFactorCodeManagerInterface::class,
                'generateAndSend',
                [$user->getUserIdentifier(), $request->getClientIp(), 'remember_me_refresh']
            );
        } catch (\Throwable $e) {
            $this->logger->critical('Échec de la génération/envoi du code MFA (revalidation périodique).', [
                'user'  => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Met à jour le pouls d'activité, avec throttle — cf. docblock de
     * ACTIVITY_TOUCH_THROTTLE_SECONDS. Volontairement silencieux en cas
     * d'erreur de sauvegarde : un échec ici ne doit jamais bloquer une
     * requête déjà légitimement autorisée à passer.
     */
    private function touchTwoFactorActivity(SonataUser $user): void
    {
        $lastCheckedAt = $user->getTwoFactorLastCheckedAt();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if (null !== $lastCheckedAt
            && ($now->getTimestamp() - $lastCheckedAt->getTimestamp()) < self::ACTIVITY_TOUCH_THROTTLE_SECONDS
        ) {
            return;
        }

        try {
            $user->setTwoFactorLastCheckedAt($now);
            $this->userManager->save($user);
        } catch (\Throwable $e) {
            $this->logger->error('Échec de la mise à jour du pouls d\'activité MFA (non bloquant).', [
                'user'  => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildRedirectResponse(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success'           => false,
                'twoFactorRequired' => true,
                'redirect'          => $this->router->generate('app_two_factor_show'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->router->generate('app_two_factor_show'));
    }
}