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

use App\Infrastructure\Security\BrowserRequestValidator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Intercepte toutes les requêtes HTTP et délègue la validation navigateur
 * à BrowserRequestValidator.
 *
 * Ordre d'exécution sur KernelEvents::REQUEST pour eQapitalise :
 *   1100 → BrowserChallengeListener   (preuve JS, pose/vérifie le cookie)
 *   1000 → BrowserValidationListener  (ce fichier — UA/headers/Sec-Fetch)
 *    900 → RateLimitListener global_requests/api_anonymous (à créer, cf. audit)
 *          + login_throttling natif du firewall `admin` (déjà actif)
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 1000)]
final readonly class BrowserValidationListener
{
    public function __construct(
        private BrowserRequestValidator $validator,
        private ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $validation = $this->shouldValidatePath($path); // 'none' | 'public' | 'sensitive'

        if ($validation === 'none') {
            return;
        }

        $isValid = match ($validation) {
            'sensitive' => $this->validator->isValidBrowserRequest($request),
            'public' => $this->validator->isValidPublicRequest($request),
            default => true,
        };

        if ($isValid) {
            return;
        }

        $clientMessage = $this->validator->getBlockReasonMessage($request);
        $reasonCode = $this->validator->getBlockReasonCode($request);

        $event->setResponse(
            new JsonResponse(
                [
                    'status' => 'error',
                    'message' => $clientMessage,
                    'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                ],
                Response::HTTP_NOT_EXTENDED
            )
        );

        $this->logBlockedRequest($request, $clientMessage, $reasonCode);
    }

    /**
     * STRATÉGIE :
     * 1. Exclure d'abord les chemins exemptés (assets, callbacks, health…).
     * 2. Inclure ensuite les chemins sensibles (admin, comptes, vérifications…).
     * 3. Par défaut : validation légère sur les pages publiques connues,
     *    sinon aucune validation (permissif pour SEO/bots légitimes).
     */
    private function shouldValidatePath(string $path): string
    {
        $excludedPrefixes = [
            '/health',
            '/ping',
            '/webhook',
            '/payment/callback',
            '/bundles/',
            '/assets/',
            '/build/',
            '/_profiler',
            '/_wdt',
            '/.well-known/',
            '/favicon.ico',
            '/robots.txt',
            '/sitemap.xml',
        ];

        foreach ($excludedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return 'none';
            }
        }

        // ── Chemins sensibles réels d'eQapitalise ──────────────────────────
        // /admin couvre : /admin/form/login, /admin/login_check_json,
        // /admin/logout, /admin/my_account/*, tout le CRUD Sonata.
        $sensitivePrefixes = [
            '/api/',
            '/api',
            '/admin',
            '/profile',
            '/verify-email',                 // EmailVerificationController
            '/ask_to_account/verifier',      // AccountRequestVerificationController
            // Prêts pour quand ces modules seront branchés :
            '/reset-password',
            '/change-password',
             '/reset-password/confirm',
             '/forget-password-user-admin',
            '/2fa',
        ];

        foreach ($sensitivePrefixes as $prefix) {
            if (str_starts_with($path, $prefix) || $path === $prefix) {
                return 'sensitive';
            }
        }

        // ── Pages publiques → validation légère (bloque pentest, autorise crawlers) ──
        $publicPrefixes = [
            '/',
            '/ask_to_account', // formulaire public de demande de compte (hors vérification)
        ];

        foreach ($publicPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return 'public';
            }
        }

        return 'none';
    }

    private function logBlockedRequest(
        \Symfony\Component\HttpFoundation\Request $request,
        string $clientMessage,
        string $reasonCode
    ): void {
        $this->logger?->warning(
            '[BrowserValidation] Requête bloquée',
            [
                'reason_code' => $reasonCode,
                'reason_message' => $clientMessage,
                'ip' => $request->getClientIp(),
                'method' => $request->getMethod(),
                'path' => $request->getPathInfo(),
                'user_agent' => $request->headers->get('User-Agent', 'absent'),
                'accept' => $request->headers->get('Accept', 'absent'),
                'accept_lang' => $request->headers->get('Accept-Language', 'absent'),
                'accept_enc' => $request->headers->get('Accept-Encoding', 'absent'),
                'referer' => $request->headers->get('Referer', 'absent'),
                'origin' => $request->headers->get('Origin', 'absent'),
                'sec_fetch_site' => $request->headers->get('Sec-Fetch-Site', 'absent'),
                'sec_fetch_mode' => $request->headers->get('Sec-Fetch-Mode', 'absent'),
                'sec_fetch_dest' => $request->headers->get('Sec-Fetch-Dest', 'absent'),
                'x_forwarded' => $request->headers->get('X-Forwarded-For', 'absent'),
                'protocol' => $request->getProtocolVersion(),
                'cookies_count' => $request->cookies->count(),
            ]
        );
    }
}
