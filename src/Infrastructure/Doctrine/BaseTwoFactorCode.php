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

namespace Wlindabla\UserBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping as ORM;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Enum\TwoFactorCodeStatus;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Model\AbstractTwoFactorCode;

/**
* One-time MFA code sent via email (13 digits + 7 letters). 
*
* One row per issued code (no in-place updates of the same record
* for "resending": each resend creates a new PENDING row and
* invalidates previous ones—maintaining a complete audit trail). 
*
* @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
*/
#[ORM\MappedSuperclass]
abstract class BaseTwoFactorCode extends AbstractTwoFactorCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected int|string|null $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    protected string $userIdentifier;

    /** Hash bcrypt/argon2 du code (via TokenHasherInterface). Jamais le code en clair. */
    #[ORM\Column(type: 'string', length: 255)]
    protected string $codeHash;

    #[ORM\Column(type: 'string', length: 20, enumType: TwoFactorCodeStatus::class)]
    protected TwoFactorCodeStatus $status = TwoFactorCodeStatus::PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeInterface $expiresAt;

    #[ORM\Column(type: 'smallint')]
    protected int $attempts = 0;

    #[ORM\Column(type: 'smallint')]
    protected int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeInterface $lastSentAt = null;

    #[ORM\Column(type: 'smallint')]
    protected int $sendCount = 1;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    protected ?string $ipAddress = null;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeInterface $createdAt;
}
