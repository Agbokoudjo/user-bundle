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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;
use Wlindabla\UserBundle\Domain\User\Service\EmailVerificationInterface;
use Wlindabla\UserBundle\Infrastructure\Routing\GeneratesOptionalRouteUrlTrait;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsController]
#[Route(
    '/verify-email/{token}/{id}',
    name: 'app.verify.email',
    methods: ['GET'],
    requirements: ['token' => '[a-zA-Z0-9]{32,64}'],
)]
final class EmailVerificationController extends AbstractController
{
    use GeneratesOptionalRouteUrlTrait ;

    public function __construct(
    private readonly EmailVerificationInterface $verificationService,
    private readonly TemplateRegistryInterface $templateRegistry,
    private readonly TranslatorInterface $translator,
    private readonly  UrlGeneratorInterface $urlGenerator,
    private readonly ?string $loginRouteName = null,
    private readonly ?string $homeRouteName = null,
    private readonly ?string $siteName = null,
    private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(string $token, int $id): Response
    {
        try {
            $this->verificationService->verifyEmail($token, $id);

            $this->addFlash('success', $this->translator->trans('email_verification.success', [], 'WlindablaUserBundle'));

            return $this->renderResult();
        } catch (InvalidTokenException $e) {
            if ($e->isExpired()) {
                return $this->handleExpiredToken($id);
            }

            return $this->handleInvalidToken($e);
        } catch (\Throwable $e) {
            return $this->handleUnexpectedError($e);
        }
    }

    /**
     * Isolated in its own try/catch: a failure here (e.g. the resend
     * cooldown from SecureTokenService) is not covered by the outer
     * try/catch of __invoke() — a catch block is not protected by the try
     * that triggered it — so without this it could crash the page it was
     * meant to keep friendly.
     */
    private function handleExpiredToken(int $id): Response
    {
        try {
            $this->verificationService->resendVerificationEmail($id);

            $this->addFlash('warning', $this->translator->trans('email_verification.expired_resent', [], 'WlindablaUserBundle'));
        } catch (\Throwable $e) {
            $this->logger?->warning('Failed to resend the verification email after an expired token.', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ]);

            $this->addFlash('warning', $this->translator->trans('email_verification.expired_resend_failed', [], 'WlindablaUserBundle'));
        }

        return $this->renderResult();
    }

    private function handleInvalidToken(InvalidTokenException $e): Response
    {
        if ($e->isAlreadyUsed()) {
            $this->addFlash('warning', $this->translator->trans('email_verification.already_used', [], 'WlindablaUserBundle'));

            return $this->renderResult();
        }

        if ($e->isUserNotFound()) {
            $this->addFlash('danger', $this->translator->trans('email_verification.user_not_found', [], 'WlindablaUserBundle'));
        } else {
            $this->addFlash('danger', $this->translator->trans('email_verification.invalid', [], 'WlindablaUserBundle'));
        }

        return $this->renderResult();
    }

    private function handleUnexpectedError(\Throwable $exception): Response
    {
        $this->logger?->critical('Unexpected error during email verification.', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->addFlash('error', $this->translator->trans('email_verification.unexpected_error', [], 'WlindablaUserBundle'));

        return $this->renderResult();
    }

    /**
     * One template for every outcome; only the flash messages differ.
     * login_url/home_url are null when the corresponding route isn't
     * configured, or doesn't exist — the template decides whether to
     * render the link at all, the page itself never crashes on a
     * missing/misconfigured route.
     */
    private function renderResult(): Response
    {
        return $this->render($this->templateRegistry->get('email_verification_result'), [
            'login_url' => $this->safeGenerateUrl($this->loginRouteName,$this->urlGenerator,$this->logger),
            'home_url' => $this->safeGenerateUrl($this->homeRouteName,$this->urlGenerator,$this->logger),
            'site_name' => $this->siteName,
        ]);
    }
}