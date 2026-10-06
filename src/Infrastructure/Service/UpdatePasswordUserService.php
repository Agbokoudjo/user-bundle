<?php

declare(strict_types=1);

namespace Wlindabla\UserBundle\Infrastructure\Service;

use Psr\Log\LoggerInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Service\UpdatePasswordUserServiceInterface;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\CacheUserProviderInterface;

/**
* Manages secure password updates with audit logging. 
*  @author AGBOKOUDJO Franck
*/
final readonly class UpdatePasswordUserService implements UpdatePasswordUserServiceInterface
{
    public function __construct(
        private UserManagerInterface $userManager,
        private LoggerInterface $logger,
        private CacheUserProviderInterface $userProvider,
    ) {}

    public function handle(
        string|int $userId,
        string $plainPassword
    ): void {
        try {
            $user = $this->userManager->find($userId);

            if (null === $user) {
                $this->logger->warning('Attempt to change the password for a non-existent user.', [
                    'user_id' => $userId
                ]);
                return;
            }

            // Password update
            $user->setPlainPassword($plainPassword);
            $this->userManager->updatePassword($user);

            // Force a refresh of the metadata (salt, updatedAt, etc.)
            $user->setUpdatedAt(new \DateTime('now',new \DateTimeZone("UTC")));
            $this->userManager->save($user);
            
            $this->userProvider->invalidateUserCache($user->getId()) ;

            // SUCCESS LOG: We track the action (without ever logging the password!)
            $this->logger->info('Password successfully updated.', [
                'user_id' => $user->getId(),
                'username' => $user->getUsername()
            ]);
        } catch (\Throwable $th) {
            // ERROR LOG: We trace the exception for debugging
            $this->logger->error('Password update failed.', [
                'user_id' => $userId,
                'error' => $th->getMessage()
            ]);

            throw $th;
        }
    }
}
