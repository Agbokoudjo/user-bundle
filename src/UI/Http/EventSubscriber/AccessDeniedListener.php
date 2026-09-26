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

use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;

/**
 * Renders a friendly 403 page (or JSON body for XHR/API requests) instead
 * of the framework's default exception page — only for already
 * authenticated users. An anonymous user hitting AccessDeniedException is
 * deliberately left alone here, so Symfony's authentication entry point
 * can still redirect them to the login page as normal.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class AccessDeniedListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly Environment $twig,
        private readonly TemplateRegistryInterface $templateRegistry,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onAccessDenied', 2],
        ];
    }

    public function onAccessDenied(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof AccessDeniedException) {
            return;
        }

        if (!$this->security->getUser()) {
            return;
        }

        $this->logger->info('Access denied.', [
            'reason' => $exception->getMessage(),
            'path' => $event->getRequest()->getPathInfo(),
        ]);

        $message = $this->translator->trans('access_denied.message', [], 'WlindablaUserBundle');
        $request = $event->getRequest();

        if ($request->isXmlHttpRequest() || 'json' === $request->getPreferredFormat()) {
            $event->setResponse(new JsonResponse(
                [
                    'title' => $this->translator->trans('access_denied.title', [], 'WlindablaUserBundle'),
                    'message' => $message,
                ],
                Response::HTTP_FORBIDDEN,
            ));

            return;
        }

        $event->setResponse(new Response(
            $this->twig->render($this->templateRegistry->get('access_denied'), [
                'message' => $message,
            ]),
            Response::HTTP_FORBIDDEN,
        ));
    }
}