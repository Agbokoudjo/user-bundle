<?php

declare(strict_types=1);

/*
 * This file is part of the project by AGBOKOUDJO Franck.
 *
 * (c) AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * Company: INTERNATIONALES WEB APPS & SERVICES
 */

namespace App\UI\Http\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * AUDIT : config/packages/rate_limiter.yaml définit `global_requests` et
 * `api_anonymous`, mais aucun listener ne les consommait — donc aucune
 * protection DDoS réelle sur les pages Twig ni sur l'API anonyme malgré
 * la config présente. Ce listener corrige ce trou.
 *
 * Priorité 900 → s'exécute après BrowserChallengeListener (1100) et
 * BrowserValidationListener (1000), donc uniquement sur du trafic déjà
 * reconnu "navigateur" ou déjà passé le challenge JS — pas de gaspillage
 * de quota sur du trafic qui sera de toute façon rejeté avant.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 900)]
final readonly class GlobalRateLimitListener
{
    public function __construct(
        private RateLimiterFactory $globalRequestsLimiter,
        private RateLimiterFactory $apiAnonymousLimiter,
        private ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $ip = $request->getClientIp() ?? 'unknown';
        $path = $request->getPathInfo();

        // Assets/health déjà exemptés en amont par BrowserValidationListener,
        // mais on double-vérifie ici pour éviter de consommer un budget.
        if (
            str_starts_with($path, '/bundles/')
            || str_starts_with($path, '/assets/')
            || str_starts_with($path, '/build/')
            || str_starts_with($path, '/health')
            || str_starts_with($path, '/_profiler')
            || str_starts_with($path, '/_wdt')
        ) {
            return;
        }

        $isApiPath = str_starts_with($path, '/api');
        $limiter = ($isApiPath ? $this->apiAnonymousLimiter : $this->globalRequestsLimiter)->create($ip);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->logger?->warning('[GlobalRateLimit] IP throttlée', [
                'ip' => $ip,
                'path' => $path,
                'retry_after' => $limit->getRetryAfter()->getTimestamp() - time(),
            ]);

            $response = new JsonResponse(
                ['status' => 'error', 'message' => 'Trop de requêtes. Réessayez dans quelques instants.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
            $response->headers->set('Retry-After', (string) ($limit->getRetryAfter()->getTimestamp() - time()));
            $event->setResponse($response);
        }
    }
}
