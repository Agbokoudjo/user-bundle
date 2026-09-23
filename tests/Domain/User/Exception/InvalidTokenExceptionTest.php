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

namespace Wlindabla\UserBundle\Tests\Domain\User\Exception;

use PHPUnit\Framework\TestCase;
use Wlindabla\UserBundle\Domain\User\Exception\EmailAlreadyVerifiedException;
use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class InvalidTokenExceptionTest extends TestCase
{
    public function testInvalidTokenNeverLeaksDebugContextInTheMessage(): void
    {
        $exception = InvalidTokenException::invalidToken('hash mismatch for selector abc123');

        self::assertStringNotContainsString('abc123', $exception->getMessage());
        self::assertSame('hash mismatch for selector abc123', $exception->getDebugContext());
    }

    public function testExpiredTokenExposesDateAsTranslationParameterOnly(): void
    {
        $expiredAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $exception = InvalidTokenException::expiredToken($expiredAt);

        self::assertTrue($exception->isExpired());
        self::assertStringNotContainsString('2026', $exception->getMessage());
        self::assertSame('2026-01-01T00:00:00+00:00', $exception->getTranslationParameters()['%expired_at%']);
    }

    public function testAlreadyUsedTokenIsDetected(): void
    {
        self::assertTrue(InvalidTokenException::alreadyUsedToken()->isAlreadyUsed());
    }

    public function testUserNotFoundIsDetected(): void
    {
        self::assertTrue(InvalidTokenException::userNotFound()->isUserNotFound());
    }

    public function testEmailAlreadyVerifiedCarriesTheEmailAsTranslationParameter(): void
    {
        $exception = EmailAlreadyVerifiedException::forEmail('franck@example.com');

        self::assertSame('franck@example.com', $exception->getTranslationParameters()['%email%']);
        self::assertSame('invalid_token.email_already_verified', $exception->getTranslationKey());
    }
}