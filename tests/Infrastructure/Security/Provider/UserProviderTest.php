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

namespace Wlindabla\UserBundle\Tests\Infrastructure\Security\Provider;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\ShareServiceBundle\Serializer\SerializerFacade;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\UserProvider;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class UserProviderTest extends TestCase
{
    public function testInvalidateUserCacheLogsInsteadOfSwallowingFailures(): void
    {
        $userManager = $this->createMock(UserManagerInterface::class);
        $userManager->method('find')->willThrowException(new \RuntimeException('DB down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Failed to invalidate the user cache.',
            self::callback(static fn (array $ctx) => 'DB down' === $ctx['error']),
        );

        $provider = new UserProvider(
            $userManager,
            $this->createMock(TagAwareCacheInterface::class),
            $this->createMock(SerializerFacade::class),
            $this->createMock(AsyncMethodDispatcherInterface::class),
            $logger,
        );

        $provider->invalidateUserCache(123);
    }

    public function testRefreshUserUsesConfiguredTtlNotAHardcodedOne(): void
    {
        $userManager = $this->createMock(UserManagerInterface::class);
        $userManager->method('getClass')->willReturn(\stdClass::class);

        $capturedTtl = null;

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->method('get')->willReturnCallback(
            function (string $key, callable $callback) use (&$capturedTtl) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter')->willReturnCallback(function (int $seconds) use (&$capturedTtl, $item) {
                    $capturedTtl = $seconds;

                    return $item;
                });
                $item->method('tag')->willReturnSelf();

                return $callback($item);
            },
        );

        $serializer = $this->createMock(SerializerFacade::class);
        $serializer->method('normalize')->willReturn(['id' => 1]);
        $serializer->method('denormalize')->willThrowException(new UserNotFoundException());

        $userManager->method('findUserByUsernameOrEmail')->willReturn(null);

        $provider = new UserProvider(
            $userManager,
            $cache,
            $serializer,
            $this->createMock(AsyncMethodDispatcherInterface::class),
            $this->createMock(LoggerInterface::class),
            expiresAfter: 300,
        );

        $user = $this->createMock(\Wlindabla\UserBundle\Infrastructure\Model\UserInterface::class);
        $user->method('getId')->willReturn(1);
        $user->method('getUserIdentifier')->willReturn('franck');

        try {
            $provider->refreshUser($user);
        } catch (UserNotFoundException) {
            // attendu ici (findUserByUsernameOrEmail renvoie null dans ce test) —
            // seul capturedTtl nous intéresse
        }

        self::assertSame(300, $capturedTtl);
    }
}