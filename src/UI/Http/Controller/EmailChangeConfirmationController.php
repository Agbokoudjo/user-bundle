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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Application\Manager\EmailChangeRequestServiceInterface;
use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;
use Wlindabla\UserBundle\UI\Http\Controller\OutcomePageRenderer;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsController]
final class EmailChangeConfirmationController extends AbstractController implements RenderResponseInterface
{
    public function __construct(
        private readonly EmailChangeRequestServiceInterface $emailChangeRequestService,
        private readonly OutcomePageRenderer $outcomePageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/email-change/confirm-old/{token}/{id}', name: 'wlindabla_user_email_change_confirm_old', methods: ['GET'], requirements: ['token' => '[a-zA-Z0-9]{32,64}'])]
    public function confirmOld(string $token, int $id): Response
    {
        try {
            $this->emailChangeRequestService->confirmOldEmail($token, $id);
            $this->addFlash('success', $this->trans('email_change.old_confirmed'));
        } catch (InvalidTokenException $e) {
            $this->handleInvalidToken($e);
        } catch (\Throwable $e) {
            $this->handleUnexpectedError($e, 'old-address confirmation');
        }

        return $this->outcomePageRenderer->render($this);
    }

    #[Route('/email-change/confirm-new/{token}/{id}', name: 'wlindabla_user_email_change_confirm_new', methods: ['GET'], requirements: ['token' => '[a-zA-Z0-9]{32,64}'])]
    public function confirmNew(string $token, int $id): Response
    {
        try {
            $this->emailChangeRequestService->confirmNewEmail($token, $id);
            $this->addFlash('success', $this->trans('email_change.new_confirmed'));
        } catch (InvalidTokenException $e) {
            $this->handleInvalidToken($e);
        } catch (\Throwable $e) {
            $this->handleUnexpectedError($e, 'new-address confirmation');
        }

        return $this->outcomePageRenderer->render($this);
    }

    #[Route('/email-change/not-me/{token}/{id}', name: 'wlindabla_user_email_change_not_me', methods: ['GET'], requirements: ['token' => '[a-zA-Z0-9]{32,64}'])]
    public function notMe(string $token, int $id): Response
    {
        try {
            $this->emailChangeRequestService->cancelByNotMe($token, $id);
            $this->addFlash('warning', $this->trans('email_change.not_me_cancelled'));
        } catch (InvalidTokenException $e) {
            $this->handleInvalidToken($e);
        } catch (\Throwable $e) {
            $this->handleUnexpectedError($e, '"not me" cancellation');
        }

        return $this->outcomePageRenderer->render($this);
    }

    private function handleInvalidToken(InvalidTokenException $e): void
    {
        $key = match (true) {
            $e->isExpired() => 'email_change.link_expired',
            $e->isAlreadyUsed() => 'email_change.link_already_used',
            default => 'email_change.link_invalid',
        };

        $this->addFlash($e->isExpired() || $e->isAlreadyUsed() ? 'warning' : 'danger', $this->trans($key));
    }

    private function handleUnexpectedError(\Throwable $e, string $context): void
    {
        $this->logger?->critical(\sprintf('Unexpected error during %s.', $context), [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->addFlash('danger', $this->trans('email_change.unexpected_error'));
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'WlindablaUserBundle');
    }

    public function renderResponse(string $view, array $parameters = [], ?Response $response = null): Response{
        return $this->render($view,$parameters,$response) ;
    }
}