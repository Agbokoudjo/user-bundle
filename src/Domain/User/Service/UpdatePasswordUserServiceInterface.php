<?php

declare(strict_types=1);

namespace Wlindabla\UserBundle\Domain\User\Service;

/**
* Manages secure password updates with audit logging. 
* @author AGBOKOUDJO Franck
*/
interface UpdatePasswordUserServiceInterface
{
    public function handle(
        string|int $userId,
        string $plainPassword
    ): void ;
}
