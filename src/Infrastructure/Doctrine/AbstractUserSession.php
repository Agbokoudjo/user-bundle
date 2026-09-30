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
use Symfony\Component\Serializer\Attribute\Groups;
use Wlindabla\UserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;
use Wlindabla\UserBundle\Domain\UserSession\Model\BaseUserSession;

/**
 * Doctrine mapping for a user session. Extend this class in your
 * application (e.g. App\Entity\UserSession) — the same MappedSuperclass
 * pattern as BaseUser and UserToken.
 *
 *   #[ORM\Entity]
 *   #[ORM\Table(name: 'user_sessions')]
 *   class BaseUserSession extends \Wlindabla\UserBundle\Infrastructure\Doctrine\UserSession {}
 *
 * A soft-deleted (revoked) session is kept for audit — ignored by
 * SessionControlListener via its `active` flag, not actually removed.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
// #[ORM\Index(columns: ['user_identifier'], name: 'idx_user_session_identifier')]
// #[ORM\Index(columns: ['session_id'],      name: 'idx_user_session_id')]
// #[ORM\Index(columns: ['last_activity_at'], name: 'idx_user_session_last_activity')]
// #[ORM\Index(columns: ['active'],           name: 'idx_user_session_active')]
#[ORM\MappedSuperclass]
abstract class AbstractUserSession extends BaseUserSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['property_serializer'])]
    protected int|string|null $id = null;

    /**
     * Business identifier of the user (email, username, UUID…). Stored as
     * a string to stay independent of the concrete User entity's type.
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['property_serializer'])]
    protected string|int|null $userIdentifier = null;

    /**
     * PHP session ID (PHPSESSID). Unique: one session ID can only belong
     * to a single row.
     */
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Groups(['property_serializer'])]
    protected ?string $sessionId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['property_serializer'])]
    protected ?string $ipAddress = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Groups(['property_serializer'])]
    protected ?string $userAgent = null;

    /** SHA-256 hash of (User-Agent + Accept-Language), to detect a device change. */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    protected ?string $deviceFingerprint = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['property_serializer'])]
    protected ?\DateTimeInterface $createdAt = null;

    /**
     * Last-activity timestamp. Updated on each request via
     * updateSessionActivity() in the repository, rate-limited by a cache
     * lock to avoid a flush on every single HTTP request.
     */
    #[ORM\Column(type: 'datetime')]
    #[Groups(['property_serializer'])]
    protected ?\DateTimeInterface $lastActivityAt = null;

    /**
     * true  → current, valid session.
     * false → revoked (login on another device, or inactivity cleanup).
     */
    #[ORM\Column(type: 'boolean')]
    protected bool $active = true;

    /**
     * Revocation reason — null while active = true.
     */
    #[ORM\Column(type: 'string', length: 30, nullable: true, enumType: UserSessionRevokedReason::class)]
    #[Groups(['property_serializer'])]
    protected ?UserSessionRevokedReason $revokedReason = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updateActivity();
        $this->active = true;
    }

    public function updateActivity(): void
    {
        $this->setLastActivityAt(new \DateTime('now', new \DateTimeZone('UTC')));
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}