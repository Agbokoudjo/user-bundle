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

namespace Wlindabla\UserBundle\Tests\Infrastructure\Templating;

use PHPUnit\Framework\TestCase;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistry;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class TemplateRegistryTest extends TestCase
{
    public function testReturnsTheConfiguredTemplate(): void
    {
        $registry = new TemplateRegistry(['email_verification' => 'custom/path.html.twig']);

        self::assertSame('custom/path.html.twig', $registry->get('email_verification'));
    }

    public function testThrowsAClearErrorForAnUnknownTemplateName(): void
    {
        $registry = new TemplateRegistry([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('wlindabla_user.templates.email_verification');

        $registry->get('email_verification');
    }
}