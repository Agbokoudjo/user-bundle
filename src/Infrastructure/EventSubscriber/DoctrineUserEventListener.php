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

namespace Wlindabla\UserBundle\Infrastructure\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\UpdateUserProfileHandler;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Domain\User\Service\UpdatePasswordUserServiceInterface;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\CacheUserProviderInterface;

/**
 * Listens to raw Doctrine hooks so user changes are still handled when
 * SonataAdmin's own persistence events are not the path used (console
 * commands, registration outside the admin screen, other bundles writing
 * to the User entity directly).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsDoctrineListener(event: Events::postPersist, priority: 100)]
#[AsDoctrineListener(event: Events::postUpdate, priority: 100)]
#[AsDoctrineListener(event: Events::preUpdate, priority: 100)]
final class DoctrineUserEventListener
{
    /**
     * Fields whose change alone must still invalidate the cached user,
     * even when username/email haven't changed — enabling/disabling an
     * account or changing its roles must take effect without waiting for
     * the cache TTL.
     */
    private const array CACHE_SENSITIVE_FIELDS = ['enabled', 'roles'];

    /**
     * Fields whose change is worth recomputing canonical fields/slug for
     * via UpdateUserProfileHandler.
     */
    private const array PROFILE_SENSITIVE_FIELDS = ['username', 'email'];

    /**
     * Delay in milliseconds before running the async UpdatePasswordUserHandler.
     * Gives Sonata time to finish its flush + DB commit. 45 seconds is
     * comfortably enough in every case observed so far.
     */
    private const int UPDATE_PASSWORD_SYNC_DELAY_MS = 45000;

    public function __construct(
        private readonly AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        private readonly CacheUserProviderInterface $userProvider,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    final public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof BaseUserInterface) {
            return;
        }

        $this->dispatchUpdateCommand($entity);
    }

    final public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof BaseUserInterface) {
            return;
        }

        $plainPassword = $entity->getPlainPassword();

        if ($plainPassword) {
            $this->asyncMethodDispatcher->dispatch(
                UpdatePasswordUserServiceInterface::class,
                'handle',
                [$entity->getId(), $plainPassword],
                new \DateTimeImmutable(
                    \sprintf('+%d milliseconds', self::UPDATE_PASSWORD_SYNC_DELAY_MS),
                    new \DateTimeZone('UTC'),
                ),
            );
        }
    }

    final public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof BaseUserInterface) {
            return;
        }

        $changeSet = $this->entityManager->getUnitOfWork()->getEntityChangeSet($entity);

        // Cache invalidation is checked independently of the profile-sync
        // guard below: enabled/roles must take effect immediately even on
        // an update that doesn't touch username/email.
        if ($this->hasAnyChangedField($changeSet, self::CACHE_SENSITIVE_FIELDS)) {
            $this->userProvider->invalidateUserCache($entity->getId());
        }

        // dispatchUpdateCommand() re-canonicalizes username/email and
        // regenerates the slug — only needed when those fields actually
        // changed, not on every update (token, password, slug...). This
        // filter is what was missing before: SecureTokenService::
        // generateEmailConfirmationToken() (confirmationToken/
        // tokenRequestedAt changing on every call, unrelated to canonical
        // fields) was re-triggering this same handler in a loop,
        // diagnosed on 2026-07-28 across several projects.
        if (!$this->hasAnyChangedField($changeSet, self::PROFILE_SENSITIVE_FIELDS)) {
            return;
        }

        $this->dispatchUpdateCommand($entity);
        $this->userProvider->invalidateUserCache($entity->getId());
    }

    /**
     * @param array<string, mixed> $changeSet
     * @param list<string>         $fields
     */
    private function hasAnyChangedField(array $changeSet, array $fields): bool
    {
        foreach ($fields as $field) {
            if (isset($changeSet[$field])) {
                return true;
            }
        }

        return false;
    }

    private function dispatchUpdateCommand(BaseUserInterface $entity): void
    {
        $this->asyncMethodDispatcher->dispatch(UpdateUserProfileHandler::class, 'handle', [$entity->getId()]);
    }
}