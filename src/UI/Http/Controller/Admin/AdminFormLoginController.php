<?php

declare(strict_types=1);

/*
 * This file is part of the eQapitalise project.
 *
 * (c) AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * Company: INTERNATIONALES WEB APPS & SERVICES
 */

namespace Wlindabla\UserBundle\UI\Http\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
#[AsController]
#[Route('/admin/form/login', name: 'app_admin_user_login', methods: ['GET', 'POST'])]
final class AdminFormLoginController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator,
        private ?CsrfTokenManagerInterface $csrfTokenManager = null,
    ) {}

    public function __invoke(AuthenticationUtils $authenticationUtils): Response
    {
        // Redirection automatique si l'utilisateur est déjà connecté avec un rôle admin habilité
        if ($this->getUser()) {
            $this->addFlash(
                    'sonata_user_error',
                    $this->translator->trans('sonata_user_already_authenticated', [], 'SonataUserBundle')
                );
            return $this->redirectToRoute('sonata_admin_dashboard');
        }

        $csrfToken = null;
        if (null !== $this->csrfTokenManager) {
            $csrfToken = $this->csrfTokenManager->getToken('authenticate')->getValue();
        }

        //on devrait fait en sorte que le dev configure le chemin de la page de login claude Ai devrait nous donnéer une idée
        return $this->render('bundles/SonataUserBundle/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
            'csrf_token' => $csrfToken,
        ]);
    }
}
