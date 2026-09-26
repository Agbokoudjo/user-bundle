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

namespace Wlindabla\UserBundle\Tests\UI\Http\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\UserBundle\Domain\User\Exception\EntityInUseException;
use Wlindabla\UserBundle\UI\Http\EventSubscriber\EntityInUseExceptionListener;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class EntityInUseExceptionListenerTest extends TestCase
{
    private function event(Request $request, \Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }

    public function testXhrRequestKeepsTheJsonResponseInsteadOfBeingOverwrittenByARedirect(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Cannot delete');

        $listener = new EntityInUseExceptionListener($translator);

        $request = Request::create('/admin/user/delete/1', 'DELETE');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $event = $this->event($request, new EntityInUseException('Still assigned to 3 people.'));
        $listener->onEntityInUse($event);

        self::assertInstanceOf(JsonResponse::class, $event->getResponse());
    }

    public function testRedirectIgnoresAnExternalRefererAndFallsBackToRoot(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $listener = new EntityInUseExceptionListener($translator);

        $request = Request::create('/admin/user/delete/1', 'DELETE');
        $request->headers->set('referer', 'https://evil.example.com/phishing');

        $event = $this->event($request, new EntityInUseException('Still assigned to 3 people.'));
        $listener->onEntityInUse($event);

        self::assertSame('/', $event->getResponse()->getTargetUrl());
    }

    public function testRedirectKeepsASameSiteRefererPathAndQuery(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $listener = new EntityInUseExceptionListener($translator);

        $request = Request::create('/admin/user/delete/1', 'DELETE');
        $request->headers->set('referer', 'https://mysite.test/admin/user/list?filter=active');

        $event = $this->event($request, new EntityInUseException('Still assigned to 3 people.'));
        $listener->onEntityInUse($event);

        self::assertSame('/admin/user/list?filter=active', $event->getResponse()->getTargetUrl());
    }
}