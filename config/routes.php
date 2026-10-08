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

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Wlindabla\UserBundle\Infrastructure\Routing\WlindablaRouteLoader;

/*
 * Entry point imported by the host application:
 *
 *     # config/routes/wlindabla_user.yaml
 *     wlindabla_user:
 *         resource: '@WlindablaUserBundle/config/routes.php'
 *         # prefix: /account
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('.', WlindablaRouteLoader::TYPE);
};