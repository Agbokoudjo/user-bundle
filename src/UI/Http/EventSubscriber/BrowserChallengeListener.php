<?php

declare(strict_types=1);

/*
 * This file is part of the project by AGBOKOUDJO Franck.
 *
 * (c) AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * Company: INTERNATIONALES WEB APPS & SERVICES
 */

namespace App\UI\Http\EventSubscriber;

use App\Infrastructure\Security\BrowserChallengeSigner;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Pose un cookie signé prouvant l'exécution JavaScript, sur les routes
 * sensibles d'eQapitalise (/admin, /profile, /verify-email,
 * /ask_to_account/verifier…).
 *
 * FLOW pour une navigation GET sans cookie valide :
 *   1. On sert une page interstitielle minimale (pas le vrai contenu).
 *   2. Son <script> pose le cookie signé puis fait un reload transparent
 *      (quelques ms, invisible pour l'utilisateur — le projet utilise déjà
 *      massivement le JS donc aucune régression UX).
 *   3. Le rechargement arrive avec le cookie → passe vers BrowserValidationListener.
 *
 * curl/wget/python-requests n'exécutent jamais le <script> → bloqués sur
 * l'interstitiel, jamais de vue sur /admin/form/login ni possibilité de POST.
 *
 * Priorité 1100 → s'exécute AVANT BrowserValidationListener (1000).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 1100)]
final readonly class BrowserChallengeListener
{
    /**
     * Doit rester synchronisé avec les préfixes 'sensitive' de
     * BrowserValidationListener::shouldValidatePath().
     */
    private const array SENSITIVE_PREFIXES = [
        '/api/', '/api', '/admin', '/profile',
        '/verify-email', '/ask_to_account/verifier',
        '/reset-password', '/change-password', '/2fa',
        '/reset-password/confirm','/forget-password-user-admin'
    ];

    public function __construct(
        private BrowserChallengeSigner $signer,
        private ?LoggerInterface $logger = null,
        private bool $enabled = true,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!$this->isSensitive($path)) {
            return;
        }

        $cookieValue = $request->cookies->get(BrowserChallengeSigner::COOKIE_NAME);

        if ($this->signer->verify($request, $cookieValue)) {
            return;
        }

        $isXhr = $request->isXmlHttpRequest();
        $isMutation = \in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        // Mutations et appels XHR/fetch (ex: /admin/login_check_json,
        // sonata_spa partial loads) : on ne peut pas injecter d'interstitiel
        // transparent ici. On délègue le blocage réel à BrowserValidationListener.
        if ($isXhr || $isMutation) {
            $this->logger?->info('[BrowserChallenge] Mutation/XHR sans cookie valide — délégué au validateur UA', [
                'path' => $path,
                'method' => $request->getMethod(),
            ]);
            return;
        }

        $token = $this->signer->issue($request);

        $response = new Response(
            $this->renderChallengePage($request->getUri()),
            Response::HTTP_OK,
            ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store']
        );

        $response->headers->setCookie(
            Cookie::create(BrowserChallengeSigner::COOKIE_NAME)
                ->withValue($token)
                ->withHttpOnly(false)
                ->withSecure(true) // désactiver via $enabled=false en dev HTTP local
                ->withSameSite('Lax')
                ->withPath('/')
                ->withExpires(time() + 43200)
        );

        $event->setResponse($response);

        $this->logger?->debug('[BrowserChallenge] Interstitiel servi', ['path' => $path]);
    }

    private function isSensitive(string $path): bool
    {
        foreach (self::SENSITIVE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix) || $path === $prefix) {
                return true;
            }
        }

        return false;
    }

    private function renderChallengePage(string $redirectTo): string
    {
        $safeUrl = htmlspecialchars($redirectTo, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Vérification…</title>
</head>
<body>
<script>
    window.location.replace("{$safeUrl}");
</script>
<noscript>
    <p>Cette page nécessite un navigateur avec JavaScript activé.</p>
</noscript>
</body>
</html>
HTML;
    }
}
