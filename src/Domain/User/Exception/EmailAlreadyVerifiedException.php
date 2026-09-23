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
 * Thrown when attempting to verify an email that is already verified.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class EmailAlreadyVerifiedException extends InvalidTokenException
{
    private function __construct(string $message, array $translationParameters)
    {
        parent::__construct(
            message: $message,
            code: self::CODE_ALREADY_USED,
            translationKey: 'invalid_token.email_already_verified',
            translationParameters: $translationParameters,
        );
    }

    public static function create(): self
    {
        return new self('This email address is already verified.', []);
    }

    public static function forEmail(string $email): self
    {
        return new self(
            \sprintf('The email address for user %s is already verified. You can log in.', $email),
            ['%email%' => $email],
        );
    }
}