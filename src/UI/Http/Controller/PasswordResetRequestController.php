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
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\UserBundle\Domain\User\Service\PasswordResetInterface;
use Wlindabla\UserBundle\Infrastructure\Form\Type\PasswordResetRequestType;
use Wlindabla\UserBundle\Infrastructure\Routing\GeneratesOptionalRouteUrlTrait;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsController]
final class PasswordResetRequestController extends AbstractController implements RenderResponseInterface
{
    use GeneratesOptionalRouteUrlTrait;
    
    public function __construct(
        private readonly PasswordResetInterface $passwordResetService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly OutcomePageRenderer $outcomePageRenderer,
        private readonly TemplateRegistryInterface $templateRegistry,
        private readonly TranslatorInterface $translator,
        private readonly \Symfony\Component\Routing\Generator\UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly ?string $homeRouteName = null,
        private readonly ?string $logoutRouteName = null
    ) {
    }

    #[Route('%wlindabla_user.password_reset.request_path%', name: 'app_password_reset_request', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        #[Target('password_reset')]
        RateLimiterFactoryInterface $passwordResetLimiter,
    ): Response {
        if ($this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirect($this->generateUrl($this->homeRouteName ?? '/'));
        }

        $form = $this->createForm(PasswordResetRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $passwordResetLimiter->create($request->getClientIp());

            if (!$limiter->consume(1)->isAccepted()) {
                throw new TooManyRequestsHttpException(
                    retryAfter: 60,
                    message: $this->translator->trans('password_reset.too_many_attempts', [], 'WlindablaUserBundle'),
                );
            }

            $this->passwordResetService->requestReset($form->get('username')->getData());

            // Message volontairement générique : ne jamais confirmer/infirmer
            // l'existence d'un compte (anti-énumération).
            $this->addFlash('success', $this->translator->trans('password_reset.request_sent', [], 'WlindablaUserBundle'));

            return $this->outcomePageRenderer->render($this);
        }

        return $this->render($this->templateRegistry->get('password_reset_request'), [
            'form' => $form->createView(),
            'login_url' => $this->safeGenerateUrl($this->logoutRouteName,$this->urlGenerator, $this->logger),   // réutilise $homeRouteName déjà injecté
        ]);
    }

    public function renderResponse(string $view, array $parameters = [], ?Response $response = null): Response
    {
        return $this->render($view,$parameters, $response) ;
    }
}