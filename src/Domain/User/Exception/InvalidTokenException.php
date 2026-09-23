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

namespace Wlindabla\UserBundle\Domain\User\Exception;

/**
 * Thrown when a user verification token is invalid.
 *
 * Covers several cases of invalidity:
 * - the token does not match the stored hash
 * - the token has expired
 * - the token has already been used
 * - no user matches the token
 *
 * Mainly used for:
 * - email confirmation
 * - password reset
 * - 2FA verification
 *
 * The exception message is always safe to display to the end user. Any
 * technical detail useful for logs is carried separately via
 * getDebugContext() and getTranslationParameters() — never appended to
 * the message itself, to avoid leaking internals to the user.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
class InvalidTokenException extends \DomainException
{
    /** Error code: token invalid (does not match the stored hash). */
    public const int CODE_INVALID = 1001;

    /** Error code: token expired (validity window exceeded). */
    public const int CODE_EXPIRED = 1002;

    /** Error code: token already used (previously consumed). */
    public const int CODE_ALREADY_USED = 1003;

    /** Error code: no user found for this token. */
    public const int CODE_USER_NOT_FOUND = 1004;

    /**
     * @param array<string, string> $translationParameters
     */
    public function __construct(
        string $message = 'The verification link is invalid.',
        int $code = self::CODE_INVALID,
        private readonly string $translationKey = 'invalid_token.invalid',
        private readonly array $translationParameters = [],
        private readonly ?string $debugContext = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Translation key to use with the `WlindablaUserBundle` domain
     * (e.g. via Symfony's TranslatableMessage) instead of the raw message.
     */
    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * @return array<string, string>
     */
    public function getTranslationParameters(): array
    {
        return $this->translationParameters;
    }

    /**
     * Technical context for logs only. Never render this to the user —
     * it may contain the raw token, an internal state, etc.
     */
    public function getDebugContext(): ?string
    {
        return $this->debugContext;
    }

    /**
     * Creates an exception for an invalid token (hash mismatch).
     *
     * @param string|null $debugContext Technical detail to log, never shown to the user
     */
    public static function invalidToken(?string $debugContext = null): self
    {
        return new self(
            message: 'The verification link is invalid or has been altered.',
            code: self::CODE_INVALID,
            translationKey: 'invalid_token.invalid',
            debugContext: $debugContext,
        );
    }

    /**
     * Creates an exception for an expired token.
     */
    public static function expiredToken(?\DateTimeInterface $expiredAt = null): self
    {
        return new self(
            message: 'The verification link has expired. Please request a new one.',
            code: self::CODE_EXPIRED,
            translationKey: 'invalid_token.expired',
            translationParameters: null !== $expiredAt
                ? ['%expired_at%' => $expiredAt->format(\DateTimeInterface::ATOM)]
                : [],
        );
    }

    /**
     * Creates an exception for an already-used token.
     */
    public static function alreadyUsedToken(?\DateTimeInterface $usedAt = null): self
    {
        return new self(
            message: 'This verification link has already been used.',
            code: self::CODE_ALREADY_USED,
            translationKey: 'invalid_token.already_used',
            translationParameters: null !== $usedAt
                ? ['%used_at%' => $usedAt->format(\DateTimeInterface::ATOM)]
                : [],
        );
    }

    /**
     * Creates an exception when no user matches the token.
     */
    public static function userNotFound(): self
    {
        return new self(
            message: 'No account matches this verification link.',
            code: self::CODE_USER_NOT_FOUND,
            translationKey: 'invalid_token.user_not_found',
        );
    }

    public function isExpired(): bool
    {
        return self::CODE_EXPIRED === $this->getCode();
    }

    public function isAlreadyUsed(): bool
    {
        return self::CODE_ALREADY_USED === $this->getCode();
    }

    public function isUserNotFound(): bool
    {
        return self::CODE_USER_NOT_FOUND === $this->getCode();
    }
}