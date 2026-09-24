<?php

declare(strict_types=1);

namespace Wlindabla\UserBundle\Domain\User\Service;

/**
 * Gère la mise à jour sécurisée du mot de passe avec traçabilité.
 * @author AGBOKOUDJO Franck
 */
interface UpdatePasswordUserServiceInterface
{
    public function handle(
        string|int $userId,
        string $plainPassword
    ): void ;
}
