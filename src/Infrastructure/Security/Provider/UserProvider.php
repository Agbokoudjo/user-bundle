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

namespace Wlindabla\UserBundle\Infrastructure\Security\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\ShareServiceBundle\Serializer\SerializerFacade;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final readonly class UserProvider implements UserProviderInterface, CacheUserProviderInterface
{
    public function __construct(
        private UserManagerInterface $userManager,
        private TagAwareCacheInterface $userCacheProvider,
        private SerializerFacade $serializer,
        private AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        private LoggerInterface $logger,
        private int $expiresAfter = 43200,
    ) {
    }

    public function getUserManager(): UserManagerInterface
    {
        return $this->userManager;
    }

    /**
     * @param string $username
     */
    public function loadUserByUsername($username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    /**
     * Called only at login. Loads the user from DB and caches it.
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $this->asyncMethodDispatcher->dispatch(
            LoggerInterface::class,
            'debug',
            ['Loading user by identifier', ['identifier' => $identifier]],
        );

        $cacheKey = 'sonata_admin_user_login_cache'.md5($identifier);

        $userData = $this->userCacheProvider->get($cacheKey, function (ItemInterface $item) use ($identifier) {
            $user = $this->findUser($identifier);

            if (!$user instanceof BaseUserInterface) {
                throw new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
            }

            $item->expiresAfter($this->expiresAfter);
            $item->tag(['users', 'ADMIN', 'user_id_'.$user->getId()]);

            return $this->serializer->normalize($user, 'json', [
                'groups' => ['user:cache', 'user:security'],
                AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => fn () => null,
            ]);
        });

        $userDenormalize = $this->serializer->denormalize(
            $userData,
            $this->userManager->getClass(),
            'json',
            ['groups' => ['user:cache', 'user:security']],
        );

        // Type checked BEFORE calling any method on it — a corrupted or
        // stale cache entry must raise a clean exception, not a fatal
        // error on a call to a method that may not exist.
        if (!$userDenormalize instanceof UserInterface) {
            throw new UnsupportedUserException(\sprintf(
                'Expected an instance of %s, but got %s.',
                UserInterface::class,
                get_debug_type($userDenormalize),
            ));
        }

        if (!$userDenormalize->isEnabled()) {
            throw new UserNotFoundException(\sprintf('Username "%s" does not exist.', $identifier));
        }

        return $userDenormalize;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof UserInterface) {
            throw new UnsupportedUserException(\sprintf(
                'Expected an instance of %s, but got %s.',
                UserInterface::class,
                $user::class,
            ));
        }

        if (!$this->supportsClass($user::class)) {
            throw new UnsupportedUserException(\sprintf(
                'Expected an instance of %s, but got %s.',
                $this->userManager->getClass(),
                $user::class,
            ));
        }

        if (!$user instanceof BaseUserInterface) {//it just editor code because UserInterface don't implemented method getId()
            throw new UnsupportedUserException(\sprintf('User must implement %s', BaseUserInterface::class));
        }

        try {
            $cacheKey = \sprintf('user_%s_%s', (string) $user->getId(), md5($user->getUserIdentifier()));

            $userData = $this->userCacheProvider->get($cacheKey, function (ItemInterface $item) use ($user) {
                $freshUser = $this->findUser($user->getUserIdentifier());

                if (!$freshUser instanceof UserInterface) {
                    throw new UserNotFoundException(\sprintf(
                        'User with ID "%s" could not be reloaded.',
                        $user->getId() ?? '',
                    ));
                }

                // Was hardcoded to 43200 — ignored the configured
                // $this->expiresAfter entirely. refreshUser() runs on
                // every request of an open session, so this value matters
                // at least as much as the one used at login.
                $item->expiresAfter($this->expiresAfter);
                $item->tag(['ADMIN', 'users', 'user_id_'.$user->getId()]);

                return $this->serializer->normalize($freshUser, null, [
                    'groups' => ['user:cache', 'user:security'],
                    AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => fn () => null,
                ]);
            });

            $userDenormalize = $this->serializer->denormalize(
                $userData,
                $this->userManager->getClass(),
                'json',
                ['groups' => ['user:cache', 'user:security']],
            );

            if (!$userDenormalize instanceof UserInterface) {
                throw new UserNotFoundException(\sprintf(
                    'User with ID "%s" could not be reloaded.',
                    $user->getId() ?? '',
                ));
            }

            return $userDenormalize;
        } catch (\Throwable $th) {
            $this->asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'error',
                ['Cache error', ['error' => $th->getMessage()]],
            );

            $freshUser = $this->findUser($user->getUserIdentifier());

            if (!$freshUser instanceof UserInterface) {
                throw new UserNotFoundException(\sprintf(
                    'User with ID "%s" could not be reloaded.',
                    $user->getId() ?? '',
                ));
            }

            return $freshUser;
        }
    }

    public function invalidateUserCache(int|string $userId): void
    {
        try {
            $freshUser = $this->userManager->find($userId);

            if (!$freshUser instanceof UserInterface) {
                return;
            }

            $cacheKeyUser = 'sonata_admin_user_login_cache'.md5($freshUser->getUserIdentifier());
            $this->userCacheProvider->invalidateTags(['user_id_'.(string) $userId, 'ADMIN', 'users']);
            $this->userCacheProvider->delete($cacheKeyUser);
        } catch (\Throwable $th) {
            // A failed cache invalidation here means a disabled account or
            // a role change can stay live in another session's cache until
            // it naturally expires — worth knowing about, not swallowing.
            $this->logger->warning('Failed to invalidate the user cache.', [
                'user_id' => $userId,
                'error' => $th->getMessage(),
            ]);
        }
    }

    public function supportsClass(string $class): bool
    {
        $userClass = $this->userManager->getClass();

        return $userClass === $class || is_subclass_of($class, $userClass);
    }

    private function findUser(string $username): ?UserInterface
    {
        $user = $this->userManager->findUserByUsernameOrEmail($username);

        return $user instanceof UserInterface ? $user : null;
    }
}