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

namespace Wlindabla\UserBundle\UI\Http\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\UserBundle\Domain\User\Exception\EntityInUseException;

/**
 * Reacts to EntityInUseException (business block, never an authorization
 * question — see the exception's own docblock). Deliberately separate
 * from AccessDeniedSubscriber: the two answer different questions, no
 * reason to mix them into a single listener.
 *
 * Unlike a generic "hide everything in prod" exception listener, the
 * message of EntityInUseException is ALWAYS shown as-is, including in
 * production — it is never a technical information leak, just a useful
 * explanation ("still assigned to 3 people").
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsEventListener(event: ExceptionEvent::class, method: 'onEntityInUse', priority: 5)]
final class EntityInUseExceptionListener
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function onEntityInUse(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof EntityInUseException) {
            return;
        }

        $request = $event->getRequest();

        if ($request->isXmlHttpRequest()) {
            $event->setResponse(new JsonResponse(
                [
                    'title' => $this->translator->trans('entity_in_use.title', [], 'WlindablaUserBundle'),
                    'message' => $exception->getMessage(),
                ],
                Response::HTTP_CONFLICT,
            ));

            // Without this, execution falls through to the redirect below,
            // which replaces the JSON response the XHR caller is waiting
            // for — the bug this fix exists for.
            return;
        }

        // Plain HTML request (no JS): reproduce the same gesture a normal
        // form submission expects on error — flash message, then back to
        // the previous page.
        if ($request->hasSession()) {
            $session = $request->getSession();

            if ($session instanceof Session) {
                $session->getFlashBag()->add('danger', $exception->getMessage());
            }
        }

        $event->setResponse(new RedirectResponse($this->safeRedirectTarget($request->headers->get('referer'))));
    }

    /**
     * Only ever redirects to a same-site relative path. A Referer header
     * is client-supplied and can be forged — using it unchecked as a
     * redirect target is an open-redirect vector.
     */
    private function safeRedirectTarget(?string $referer): string
    {
        if (null === $referer) {
            return '/';
        }

        $path = parse_url($referer, \PHP_URL_PATH);

        if (!\is_string($path) || !str_starts_with($path, '/')) {
            return '/';
        }

        $query = parse_url($referer, \PHP_URL_QUERY);

        return $path.(\is_string($query) ? '?'.$query : '');
    }
}