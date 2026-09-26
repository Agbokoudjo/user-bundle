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

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;
use Wlindabla\UserBundle\Domain\User\Service\PasswordResetInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[Route(
    '/reset-password/confirm/{token}/{id}',
    name: 'app_password_reset_confirm',
    methods: ['GET'],
    requirements: [
        'token' => '[a-zA-Z0-9]{32,64}',
    ],
)]
final class PasswordResetConfirmationController extends AbstractController
{
    private const string LOGIN_ROUTE = 'app_admin_user_login';

    public function __construct(
        private readonly PasswordResetInterface $passwordResetService,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(string $token, int $id): Response
    {
        try {
            $this->passwordResetService->confirmReset($token, $id);

            $this->addFlash(
                'success',
                'Votre demande est confirmée ! Un mot de passe temporaire vient de vous être envoyé par email : connectez-vous avec, puis changez-le depuis votre profil.'
            );

            return $this->render('email/security/checkEmailpassword_reset_confirm.html.twig', [
                'page_login_url' => self::LOGIN_ROUTE,
            ]);
        } catch (InvalidTokenException $e) {
            return $this->handleInvalidToken($e);
        } catch (\Throwable $e) {
            $this->logger?->critical('Erreur inattendue lors de la confirmation de reset password', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash(
                'danger',
                '⚠️ Une erreur est survenue. Veuillez réessayer plus tard ou contacter le support.'
            );

            return $this->render('email/security/checkEmailpassword_reset_confirm.html.twig', [
                'page_redirection_url' => 'app_home',
                'page_label' => 'Aller à la page d\'accueil',
            ]);
        }
    }

    private function handleInvalidToken(InvalidTokenException $e): Response
    {
        if ($e->isExpired()) {
            $this->addFlash(
                'warning',
                'Ce lien de réinitialisation a expiré. Merci de refaire une demande de mot de passe oublié.'
            );
        } elseif ($e->isAlreadyUsed()) {
            $this->addFlash(
                'warning',
                'Ce lien a déjà été utilisé. Si besoin, refaites une demande de mot de passe oublié.'
            );
        } elseif ($e->isUserNotFound()) {
            $this->addFlash('danger', '❌ ' . $e->getMessage());
        } else {
            $this->addFlash(
                'danger',
                '❌ Le lien de réinitialisation est invalide. Veuillez réessayer.'
            );
        }

        return $this->render('email/security/checkEmailpassword_reset_confirm.html.twig', [
            'page_login_url' => self::LOGIN_ROUTE,
        ]);
    }
}
