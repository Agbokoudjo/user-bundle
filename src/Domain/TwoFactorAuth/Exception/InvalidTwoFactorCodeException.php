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

namespace Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception;

/**
 * Thrown when the submitted code is incorrect or expired.
 *
 * The message stays deliberately generic — never a detail about the exact
 * cause, so an attacker can't tell whether they're getting closer to the
 * right code.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class InvalidTwoFactorCodeException extends \RuntimeException
{
    public function __construct(
        string $message = 'The code is invalid or has expired.',
        private readonly string $translationKey = 'two_factor.invalid_code',
        /** @var array<string, string> */
        private readonly array $translationParameters = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

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
}