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

namespace Wlindabla\UserBundle\Domain\User\Event;

use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final readonly class UpdatePasswordUserEvent
{
    public function __construct(
        private BaseUserInterface $_user,
        private string $generatePassword,
    ) {}

    public function getUser(): BaseUserInterface
    {
        return $this->_user;
    }

    /**
     * Get the value of generatePassword
     */
    public function getGeneratePassword(): string
    {
        return $this->generatePassword;
    }
}
