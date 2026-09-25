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

namespace Wlindabla\UserBundle\Tests\Infrastructure\Service;

use PHPUnit\Framework\TestCase;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\ShareServiceBundle\Security\Hash\TokenHasherInterface;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\ApplyEmailVerificationHandler;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Infrastructure\Service\EmailVerificationService;
use Wlindabla\UserBundle\Infrastructure\Service\SecureTokenService;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class EmailVerificationServiceSyncApplyTest extends TestCase
{
    public function testVerifyEmailAppliesVerificationSynchronouslyNotViaAsyncDispatcher(): void
    {
        $user = $this->createMock(BaseUserInterface::class);
        $user->method('getId')->willReturn(1);
        $user->method('getConfirmationToken')->willReturn('hashed-token');
        $user->method('getTokenRequestedAt')->willReturn(new \DateTimeImmutable());

        $userManager = $this->createMock(UserManagerInterface::class);
        $userManager->method('find')->willReturn($user);

        $tokenHasher = $this->createMock(TokenHasherInterface::class);
        $tokenHasher->method('verify')->willReturn(true);

        $applyHandler = $this->createMock(ApplyEmailVerificationHandler::class);
        $applyHandler->expects(self::once())->method('handle')->with(1);

        $asyncDispatcher = $this->createMock(AsyncMethodDispatcherInterface::class);

        $dispatchedServices = [];
        $asyncDispatcher = $this->createMock(AsyncMethodDispatcherInterface::class);
        $asyncDispatcher->method('dispatch')
            ->willReturnCallback(function (string $service) use (&$dispatchedServices) {
                $dispatchedServices[] = $service;
            });

        self::assertNotContains(ApplyEmailVerificationHandler::class, $dispatchedServices);
                $service = new EmailVerificationService(
                    $userManager,
                    $tokenHasher,
                    $this->createMock(SecureTokenService::class),
                    $asyncDispatcher,
                    $applyHandler,
                );

                $service->verifyEmail('raw-token', 1);
            }
}