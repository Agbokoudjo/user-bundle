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
use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;
use Wlindabla\UserBundle\Domain\User\Service\PasswordResetInterface;
use Wlindabla\UserBundle\UI\Http\Controller\OutcomePageRenderer;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsController]
#[Route(
    '%wlindabla_user.password_reset.confirm_path%',
    name: 'app_password_reset_confirm',
    methods: ['GET'],
    requirements: ['token' => '[a-zA-Z0-9]{32,64}'],
)]
final class PasswordResetConfirmationController extends AbstractController implements RenderResponseInterface
{
    public const string ROUTE_NAME = 'app_password_reset_confirm';
    
    public function __construct(
        private readonly PasswordResetInterface $passwordResetService,
        private readonly OutcomePageRenderer $outcomePageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(string $token, int $id): Response
    {
        try {
            $this->passwordResetService->confirmReset($token, $id);

            $this->addFlash('success', $this->translator->trans('password_reset.confirmed', [], 'WlindablaUserBundle'));
        } catch (InvalidTokenException $e) {
            $this->handleInvalidToken($e);
        } catch (\Throwable $e) {
            $this->logger?->critical('Unexpected error while confirming a password reset.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('danger', $this->translator->trans('password_reset.unexpected_error', [], 'WlindablaUserBundle'));
        }

        return $this->outcomePageRenderer->render($this);
    }

    private function handleInvalidToken(InvalidTokenException $e): void
    {
        $key = match (true) {
            $e->isExpired() => 'password_reset.expired',
            $e->isAlreadyUsed() => 'password_reset.already_used',
            $e->isUserNotFound() => 'password_reset.user_not_found',
            default => 'password_reset.invalid',
        };

        $this->addFlash($e->isExpired() || $e->isAlreadyUsed() ? 'warning' : 'danger', $this->translator->trans($key, [], 'WlindablaUserBundle'));
    }

   
    public function renderResponse(string $view, array $parameters = [], ?Response $response = null): Response
    {
        return $this->render($view,$parameters, $response) ;
    }
}