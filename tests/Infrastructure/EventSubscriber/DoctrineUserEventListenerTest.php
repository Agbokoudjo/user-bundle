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

namespace Wlindabla\UserBundle\Tests\Infrastructure\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use PHPUnit\Framework\TestCase;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\UpdateUserProfileHandler;
use Wlindabla\UserBundle\Infrastructure\EventSubscriber\DoctrineUserEventListener;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\CacheUserProviderInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class DoctrineUserEventListenerTest extends TestCase
{
    private function listener(
        AsyncMethodDispatcherInterface $dispatcher,
        CacheUserProviderInterface $userProvider,
        array $changeSet,
    ): DoctrineUserEventListener {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getEntityChangeSet')->willReturn($changeSet);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        return new DoctrineUserEventListener($dispatcher, $userProvider, $entityManager);
    }

    public function testPostUpdateInvalidatesCacheWhenOnlyEnabledChanges(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getId')->willReturn(42);

        $userProvider = $this->createMock(CacheUserProviderInterface::class);
        $userProvider->expects(self::once())->method('invalidateUserCache')->with(42);

        $dispatcher = $this->createMock(AsyncMethodDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $listener = $this->listener($dispatcher, $userProvider, ['enabled' => [false, true]]);
        $listener->postUpdate(new LifecycleEventArgs($user, $this->createMock(EntityManagerInterface::class)));
    }

    public function testPostUpdateInvalidatesCacheWhenOnlyRolesChange(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getId')->willReturn(42);

        $userProvider = $this->createMock(CacheUserProviderInterface::class);
        $userProvider->expects(self::once())->method('invalidateUserCache')->with(42);

        $listener = $this->listener(
            $this->createMock(AsyncMethodDispatcherInterface::class),
            $userProvider,
            ['roles' => [['ROLE_USER'], ['ROLE_ADMIN']]],
        );
        $listener->postUpdate(new LifecycleEventArgs($user, $this->createMock(EntityManagerInterface::class)));
    }

    public function testPostUpdateSkipsBothWhenNothingRelevantChanged(): void
    {
        $user = $this->createMock(UserInterface::class);

        $userProvider = $this->createMock(CacheUserProviderInterface::class);
        $userProvider->expects(self::never())->method('invalidateUserCache');

        $dispatcher = $this->createMock(AsyncMethodDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $listener = $this->listener($dispatcher, $userProvider, ['confirmationToken' => [null, 'abc']]);
        $listener->postUpdate(new LifecycleEventArgs($user, $this->createMock(EntityManagerInterface::class)));
    }

    public function testPostUpdateDispatchesProfileHandlerWhenEmailChanges(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getId')->willReturn(42);

        $dispatcher = $this->createMock(AsyncMethodDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')
            ->with(UpdateUserProfileHandler::class, 'handle', [42]);

        $listener = $this->listener(
            $dispatcher,
            $this->createMock(CacheUserProviderInterface::class),
            ['email' => ['old@x.com', 'new@x.com']],
        );
        $listener->postUpdate(new LifecycleEventArgs($user, $this->createMock(EntityManagerInterface::class)));
    }
}