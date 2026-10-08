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

namespace Wlindabla\UserBundle\UI\Http\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\UserBundle\Domain\UserSession\Application\Manager\UserSessionManagerInterface;
use Wlindabla\UserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;
use Wlindabla\UserBundle\Infrastructure\Routing\GeneratesOptionalRouteUrlTrait;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;

/**
 * Page shown when a user tries to log in while already connected on
 * another device/browser (single-session policy).
 *
 * {id} is automatically decrypted by share-service-bundle's own
 * IdEncryptionRequestArgumentValueResolver before reaching this
 * controller.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsController]
#[Route(path: '/session-conflict/{userId}', name: 'wlindabla_user_session_conflict', methods: ['GET'])]
final class SessionConflictController extends AbstractController
{
    use GeneratesOptionalRouteUrlTrait;

    public function __construct(
        private readonly AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        private readonly UserSessionManagerInterface $sessionManager,
        private readonly TemplateRegistryInterface $templateRegistry,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $kernelEnvironment,
        private readonly ?string $homeRouteName = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(Request $request, int|string $id): Response
    {
        try {
            $this->asyncMethodDispatcher->dispatch(LoggerInterface::class, 'info', [
                'Session conflict page displayed',
                ['userId' => $id, 'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)],
            ]);

            // The losing browser must not remain "active" in the DB either:
            // explicitly revoke its own row (idempotent if already revoked
            // by revokeAllActiveSessions on the new login's side).
            if ($request->hasSession()) {
                $this->sessionManager->removeSession($request->getSession()->getId(), UserSessionRevokedReason::CONFLICT);
            }

            return $this->render($this->templateRegistry->get('session_conflict'));
        } catch (\Throwable $th) {
            $this->asyncMethodDispatcher->dispatch(LoggerInterface::class, 'error', [
                'Error while displaying the session conflict page',
                [
                    'exception_class' => $th::class,
                    'exception_message' => $th->getMessage(),
                    'user' => $id,
                    'trace' => 'prod' !== $this->kernelEnvironment ? $th->getTraceAsString() : null,
                ],
            ]);

            $this->addFlash('warning', $this->translator->trans('session_conflict.error', [], 'WlindablaUserBundle'));

            return $this->redirect($this->safeGenerateUrl($this->homeRouteName,$this->urlGenerator, $this->logger) ?? '/');
        }
    }
}