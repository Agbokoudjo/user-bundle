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

namespace Wlindabla\UserBundle\Tests\UI\Http\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;
use Wlindabla\UserBundle\UI\Http\Controller\EmailVerificationController;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class EmailVerificationControllerSafeGenerateUrlTest extends TestCase
{
    public function testSafeGenerateUrlReturnsNullInsteadOfCrashingOnAMissingRoute(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willThrowException(
            new RouteNotFoundException('Route "app_login" does not exist.'),
        );

        $controller = new EmailVerificationController(
            $this->createMock(\Wlindabla\UserBundle\Domain\User\Service\EmailVerificationInterface::class),
            $this->createMock(TemplateRegistryInterface::class),
            $this->createMock(\Symfony\Contracts\Translation\TranslatorInterface::class),
            loginRouteName: 'app_login',
        );

        $container = new \Symfony\Component\DependencyInjection\Container();
        $container->set('router', $urlGenerator);
        $controller->setContainer($container);

        $method = new \ReflectionMethod($controller, 'safeGenerateUrl');
        $method->setAccessible(true);

        self::assertNull($method->invoke($controller, 'app_login'));
    }
}