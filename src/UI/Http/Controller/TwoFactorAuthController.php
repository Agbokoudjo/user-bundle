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

namespace Wlindabla\UserBundle\UI\Http\Controller;

use App\UI\Http\EventSubscriber\TwoFactorAuthListener;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Application\Manager\TwoFactorCodeManagerInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Enum\TwoFactorAuthKey;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception\InvalidTwoFactorCodeException;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception\TwoFactorCodeLockedException;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception\TwoFactorSendThrottledException;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\CacheUserProviderInterface;
use Wlindabla\UserBundle\UI\Http\Controller\Trait\ResolvesManagedCurrentUserTrait;
/**
 * Écran de saisie du second facteur (13 chiffres + 7 lettres, envoyé
 * par email). Ces trois routes sont volontairement EXCLUES de
 * SessionControlListener (elles ne sont pas préfixées `sonata_`) et
 * explicitement autorisées par TwoFactorAuthListener tant que le 2FA
 * est en attente.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
final class TwoFactorAuthController extends AbstractController
{
    use ResolvesManagedCurrentUserTrait;

    public function __construct(
        private readonly TwoFactorCodeManagerInterface $twoFactorCodeManager,
        private readonly UserManagerInterface $userManager,
        private readonly CacheUserProviderInterface $userProvider,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/admin/2fa/show', name: 'app_two_factor_show', methods: ['GET'])]
    public function show(Request $request): Response
    {
        /**
         * @var UserInterface|null
         */
        $user=$this->getUser() ;

        if ($user === null ) {
            return $this->redirectToRoute('app_admin_user_login');
        }

        // Rien en attente (déjà validé, ou accès direct sans login préalable
        // au 2FA) → pas de raison d'être ici, retour au tableau de bord
        // (Sonata redirigera vers le login si nécessaire).
        if ($this->twoFactorCodeManager->findActivePending($user->getUserIdentifier()) === null
            && !$request->getSession()->get(TwoFactorAuthListener::PENDING_2FA, false)
        ) {
            return $this->redirectToRoute('sonata_admin_dashboard'); //Claude Ai va nous suggerer comment faire 
        }

        return $this->render('security/twoFactorAuth.html.twig', [
            'username' => $user->getUsername(),
        ]);
    }

    #[Route('/admin/2fa/verify', name: 'app_two_factor_verify', methods: ['POST'])]
    public function verify(Request $request): Response
    {
        /**
         * @var UserInterface|null
         */
        $user=$this->getUser() ;

        if ($user === null) {
            return $this->jsonOrRedirect($request, false, 'Session expirée, merci de vous reconnecter.', 'app_admin_user_login');
        }

        if (!$this->isCsrfTokenValid('two_factor_verify', $request->request->get('_csrf_token'))) {
            return $this->respondInvalid($request, 'Session expirée, merci de réessayer.');
        }

        $submittedCode = trim((string) $request->request->get('code', ''));

        if ($submittedCode === '') {
            return $this->respondInvalid($request, 'Le code est obligatoire.');
        }

        try {
            $this->twoFactorCodeManager->verify($user->getUserIdentifier(), $submittedCode);
        } catch (TwoFactorCodeLockedException $e) {
            return $this->respondInvalid($request, $e->getMessage(), Response::HTTP_LOCKED);
        } catch (InvalidTwoFactorCodeException $e) {
            return $this->respondInvalid($request, $e->getMessage());
        }

        // Régénération de l'identifiant de session : un nouvel identifiant
        // est émis une fois le second facteur validé (piège classique :
        // ne jamais garder le sessionId antérieur à la validation).
        $request->getSession()->migrate(true);
        $request->getSession()->remove(TwoFactorAuthKey::PENDING_2FA->value);

        // Fraîcheur MFA persistée sur le COMPTE (pas la session PHP, cf.
        // docblock de SonataUser::$twoFactorValidatedAt) — c'est cette
        // valeur que TwoFactorAuthListener revérifiera sur les requêtes
        // suivantes, y compris celles authentifiées par remember-me.
        //
        // resolveManagedCurrentUser() recharge $user comme une entité
        // réellement gérée par Doctrine — cf. son docblock pour le piège
        // évité (UserProvider::refreshUser() renvoie un objet désérialisé,
        // jamais géré, dont la persistance directe déclenche une
        // ré-insertion). Erreur volontairement non bloquante ici : un échec
        // de rechargement à cet instant précis ne doit jamais casser un
        // flux de connexion qui vient de réussir sa vérification de code.
        try {
            $managedUser = $this->resolveManagedCurrentUser($this->userManager,$user);
            $managedUser->markTwoFactorValidated();
            $this->userManager->save($managedUser);

            // ⚠️ Indispensable : UserProvider::refreshUser() sert un cache
            // JSON (TTL 7 jours) à CHAQUE requête suivante, y compris la
            // redirection vers le tableau de bord juste après. Sans cette
            // invalidation, TwoFactorAuthListener continuerait de voir
            // l'ancienne valeur (souvent null) de twoFactorValidatedAt et
            // redemanderait un code en boucle, malgré l'enregistrement
            // réussi ci-dessus — bug constaté en prod le 20/08.
            $this->userProvider->invalidateUserCache($managedUser->getId());

        } catch (\Throwable $e) {
            $this->logger->error('Impossible de recharger l\'utilisateur courant pour poser la fraîcheur MFA.', [
                'user'  => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }

        $this->logger->info('2FA validé, session régénérée', ['user' => $user->getUserIdentifier()]);

        return $this->jsonOrRedirect($request, true, 'Code validé.', 'sonata_admin_dashboard');
    }

    #[Route('/admin/2fa/resend', name: 'app_two_factor_resend', methods: ['POST'])]
    public function resend(
        Request $request,
        #[Target('two_factor_resend')]
        RateLimiterFactoryInterface $resendLimiter,
    ): Response {
        /**
         * @var UserInterface|null
         */
        $user=$this->getUser() ;

        if ($user === null) {
            return $this->jsonOrRedirect($request, false, 'Session expirée, merci de vous reconnecter.', 'app_admin_user_login');
        }

        if (!$this->isCsrfTokenValid('two_factor_resend', $request->headers->get('X-CSRF-Token'))) {
            return $this->jsonOrRedirect($request, false, 'Session expirée, merci de réessayer.', 'app_two_factor_show');
        }
        
        if ($this->getParameter('app.env') === 'prod') {
            $limiter = $resendLimiter->create($request->getClientIp());
            if (false === $limiter->consume(1)->isAccepted()) {
                throw new TooManyRequestsHttpException(retryAfter: 60, message: 'Trop de demandes. Réessayez plus tard.');
            }
        }

        try {
            $this->twoFactorCodeManager->generateAndSend($user->getUserIdentifier(), $request->getClientIp(), 'resend');
        } catch (TwoFactorSendThrottledException $e) {
            return $this->respondInvalid($request, $e->getMessage(), Response::HTTP_TOO_MANY_REQUESTS);
        }

        $request->getSession()->set(TwoFactorAuthListener::PENDING_2FA, true);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true, 'message' => 'Un nouveau code vous a été envoyé.']);
        }

        $this->addFlash('success', 'Un nouveau code vous a été envoyé.');

        return $this->redirectToRoute('app_two_factor_show');
    }

    private function respondInvalid(Request $request, string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'errorMessage' => $message], $status);
        }

        $this->addFlash('danger', $message);

        return $this->redirectToRoute('app_two_factor_show');
    }

    private function jsonOrRedirect(Request $request, bool $success, string $message, string $route): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success'  => $success,
                'message'  => $message,
                'redirect' => $this->generateUrl($route),
            ], $success ? Response::HTTP_OK : Response::HTTP_UNAUTHORIZED);
        }

        if (!$success) {
            $this->addFlash('danger', $message);
        }

        return $this->redirectToRoute($route);
    }
}
