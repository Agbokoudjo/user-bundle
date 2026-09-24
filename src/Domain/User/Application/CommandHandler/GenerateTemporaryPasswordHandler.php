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

namespace Wlindabla\UserBundle\Domain\User\Application\CommandHandler;

use Wlindabla\ShareServiceBundle\Bus\EventBusInterface;
use Wlindabla\ShareServiceBundle\Security\Generator\GenerateTemporaryPasswordService;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Event\UpdatePasswordUserEvent;
use Wlindabla\UserBundle\Domain\User\Service\UpdatePasswordUserServiceInterface;

final readonly class GenerateTemporaryPasswordHandler
{
    public function __construct(
        private EventBusInterface $eventBus,
        private UserManagerInterface $userManager,
        private UpdatePasswordUserServiceInterface $updatePasswordService,
        private GenerateTemporaryPasswordService $generatePasswordService){}

    public function process(int|string $userId):void{
        try {
            $plainTemporyPasswordUser = $this->generatePasswordService->generateTemporaryPassword();

            $this->updatePasswordService->handle($userId, $plainTemporyPasswordUser) ;
            
            $user = $this->userManager->find($userId);
            
            $this->eventBus->dispatch(
                new UpdatePasswordUserEvent($user, $plainTemporyPasswordUser)
            );
        } catch (\Throwable $th) {
            throw $th;
        }
    }  
}
