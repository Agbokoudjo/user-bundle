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

namespace Wlindabla\UserBundle\Domain\TwoFactorAuth\Model\Persistance;

use Wlindabla\UserBundle\Domain\TwoFactorAuth\Model\TwoFactorCodeInterface;

/**
 * Handles persistence operations for MFA codes.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
interface TwoFactorCodeRepositoryInterface
{
    /**
     * Finds the most recent PENDING code for a given user.
     *
     * Note: Non-expired bounds are intentionally excluded from the query level.
     * Expiration checking is delegated to the domain layer (via `isPending()`)
     * to enforce single-source-of-truth business rules.
     */
    public function findLatestPending(string $userIdentifier): ?TwoFactorCodeInterface;

    /**
     * Counts the number of codes sent for a user since a given timestamp.
     *
     * Used primarily for rate limiting windows (e.g., max 3 dispatches per 15 min).
     * Counts all generated records regardless of their status (PENDING or otherwise).
     */
    public function countSentSince(string $userIdentifier, \DateTimeInterface$since): int;

    /**
     * Persists a MFA code instance.
     *
     * @param bool $flush Whether to flush changes immediately to the storage backend.
     */
    public function save(TwoFactorCodeInterface $code, bool$flush = true): void;

    /**
     * Invalidates (marks as EXPIRED) all PENDING codes for a given user.
     *
     * Should be invoked prior to issuing a new code or upon successful verification
     * to prevent replay attacks using older, unexpired codes.
     */
    public function invalidatePending(string $userIdentifier): void;

    /**
     * Returns the fully qualified class name of the managed entity/model.
     *
     * @return class-string<TwoFactorCodeInterface>
     */
    public function getClass(): string;
}