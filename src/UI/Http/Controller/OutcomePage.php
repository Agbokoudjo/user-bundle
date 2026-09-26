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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wlindabla\UserBundle\Infrastructure\Routing\GeneratesOptionalRouteUrlTrait;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;
use Wlindabla\UserBundle\UI\Http\Controller\RenderResponseInterface;

/**
 * Renders the shared "outcome" page used by every controller that reacts
 * to an email link click (email verification, password reset confirm,
 * password reset request-sent) — one template, one set of login/home
 * links, only the flash messages differ per flow.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class OutcomePageRenderer
{
    use GeneratesOptionalRouteUrlTrait;

    public function __construct(
        private readonly TemplateRegistryInterface $templateRegistry,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?string $loginRouteName,
        private readonly ?string $homeRouteName,
        private readonly ?string $siteName,
        private readonly ?LoggerInterface $logger,
    ) {
    }

    public function render(RenderResponseInterface $controller): Response
    {
        return $controller->renderResponse($this->templateRegistry->get('outcome_page'), [
            'login_url' => $this->safeGenerateUrl($this->loginRouteName,$this->urlGenerator,$this->logger),
            'home_url' => $this->safeGenerateUrl($this->homeRouteName, $this->urlGenerator,$this->logger),
            'site_name' => $this->siteName,
        ]);
    }
}