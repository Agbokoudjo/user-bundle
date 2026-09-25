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
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Infrastructure\Service\EmailVerificationService;
use Wlindabla\UserBundle\Infrastructure\Service\SecureTokenService;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class EmailVerificationServiceTest extends TestCase
{
    public function testMissingTokenWithNoPriorVerificationRaisesInvalidTokenNotAlreadyUsed(): void
    {
        $user = $this->createMock(BaseUserInterface::class);
        $user->method('getId')->willReturn(1);
        $user->method('getConfirmationToken')->willReturn(null);
        $user->method('getTokenRequestedAt')->willReturn(null);
        $user->method('getEmailVerifiedAt')->willReturn(null);

        $userManager = $this->createMock(UserManagerInterface::class);
        $userManager->method('find')->willReturn($user);

        $service = new EmailVerificationService(
            $userManager,
            $this->createMock(TokenHasherInterface::class),
            $this->createMock(SecureTokenService::class),
            $this->createMock(AsyncMethodDispatcherInterface::class),
        );

        try {
            $service->verifyEmail('whatever', 1);
            self::fail('Expected InvalidTokenException.');
        } catch (\Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException $e) {
            self::assertFalse($e->isAlreadyUsed());
        }
    }

    public function testMissingTokenAfterVerificationRaisesAlreadyUsedWithRealDate(): void
    {
        $verifiedAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');

        $user = $this->createMock(BaseUserInterface::class);
        $user->method('getId')->willReturn(1);
        $user->method('getConfirmationToken')->willReturn(null);
        $user->method('getTokenRequestedAt')->willReturn(null);
        $user->method('getEmailVerifiedAt')->willReturn($verifiedAt);

        $userManager = $this->createMock(UserManagerInterface::class);
        $userManager->method('find')->willReturn($user);

        $service = new EmailVerificationService(
            $userManager,
            $this->createMock(TokenHasherInterface::class),
            $this->createMock(SecureTokenService::class),
            $this->createMock(AsyncMethodDispatcherInterface::class),
        );

        try {
            $service->verifyEmail('whatever', 1);
            self::fail('Expected InvalidTokenException.');
        } catch (\Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException $e) {
            self::assertTrue($e->isAlreadyUsed());
            self::assertSame(
                '2026-01-01T00:00:00+00:00',
                $e->getTranslationParameters()['%used_at%'],
            );
        }
    }
}