<?php

declare(strict_types=1);

namespace Wlindabla\UserBundle\UI\Http\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('%wlindabla_user.routes.logout_path%', name: '%wlindabla_user.routes.logout%', methods: ['GET'])]
final class UserLogoutController
{
    public function __invoke(Request $request): void
    {
        
    }
}
