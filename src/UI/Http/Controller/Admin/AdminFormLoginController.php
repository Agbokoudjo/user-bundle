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

namespace Wlindabla\UserBundle\UI\Http\Controller\Admin;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\UserBundle\Infrastructure\Routing\GeneratesOptionalRouteUrlTrait;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;

/**
 * Default login page shipped by the bundle. Renders the firewall's own
 * error/last-username data via AuthenticationUtils — authentication
 * itself is handled by Symfony's security firewall (form_login), not by
 * this controller, which only needs to render GET requests.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsController]
#[Route('%wlindabla_user.login_path%', name: 'wlindabla_user_login', methods: ['GET', 'POST'])]
final class AdminFormLoginController extends AbstractController
{
    use GeneratesOptionalRouteUrlTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly TemplateRegistryInterface $templateRegistry,
        private readonly ?CsrfTokenManagerInterface $csrfTokenManager = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?string $homeRouteName = null,
        private readonly ?string $siteName = null,
    ) {
    }

    public function __invoke(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            $this->addFlash('info', $this->translator->trans('login.already_authenticated', [], 'WlindablaUserBundle'));

            return $this->redirect($this->safeGenerateUrl($this->homeRouteName, $this->logger) ?? '/');
        }

        $csrfToken = null;

        if (null !== $this->csrfTokenManager) {
            $csrfToken = $this->csrfTokenManager->getToken('authenticate')->getValue();
        }

        return $this->render($this->templateRegistry->get('login'), [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
            'csrf_token' => $csrfToken,
        ]);
    }
}