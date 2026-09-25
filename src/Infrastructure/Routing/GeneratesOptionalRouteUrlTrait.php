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

namespace Wlindabla\UserBundle\Infrastructure\Routing;

use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Generates a URL for a route that may not be configured, or may not
 * exist in the consuming project — used wherever a bundle controller
 * links to a route it does not own (login page, home page), so a
 * misconfiguration never crashes the page it appears on.
 *
 * Requires the using class to have a generateUrl(string): string method
 * (AbstractController provides it).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
trait GeneratesOptionalRouteUrlTrait
{
    private function safeGenerateUrl(?string $routeName, ?LoggerInterface $logger = null): ?string
    {
        if (null === $routeName) {
            return null;
        }

        try {
            return $this->generateUrl($routeName);
        } catch (RouteNotFoundException $e) {
            $logger?->warning('Route configured for a WlindablaUserBundle controller does not exist.', [
                'route' => $routeName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    abstract protected function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string ;
}