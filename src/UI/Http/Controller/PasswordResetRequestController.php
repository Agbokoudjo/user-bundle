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

namespace App\UI\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Wlindabla\UserBundle\Domain\User\Service\PasswordResetInterface;
use Wlindabla\UserBundle\Infrastructure\Form\Type\PasswordResetRequestType;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class PasswordResetRequestController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetInterface $passwordResetService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {}

    #[Route(
        '/forget-password-user-admin',
        name: 'app_password_reset_request',
        methods: ['GET', 'POST'],
    )]
    public function __invoke(
        Request $request,
        #[Target('password_reset')]
        RateLimiterFactoryInterface $passwordResetLimiter,
    ): Response {
        if ($this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('sonata_admin_dashboard');
        }

        $form = $this->createForm(PasswordResetRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->getParameter('app.env') === 'prod') {
                $limiter = $passwordResetLimiter->create($request->getClientIp());
                if (false === $limiter->consume(1)->isAccepted()) {
                    throw new TooManyRequestsHttpException(retryAfter: 60, message: 'Trop de tentatives. Réessayez plus tard.');
                }
            }

            $this->passwordResetService->requestReset($form->get('username')->getData());

            // Message volontairement générique : ne jamais confirmer/infirmer
            // l'existence d'un compte (anti-énumération).
            $this->addFlash(
                'success',
                'Si un compte existe avec cette adresse, un email de confirmation vient de vous être envoyé.'
            );

            return $this->render('email/security/checkEmailpasswordResetRequest.html.twig', [
                'page_login_url' => 'app_admin_user_login',
            ]);
        }

        return $this->render('security/passwordResetRequest.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
