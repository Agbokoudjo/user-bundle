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

namespace Wlindabla\UserBundle\Domain\LoggerLoginUser\Model\Persistance;

use Wlindabla\UserBundle\Domain\LoggerLoginUser\Model\LoggerLoginUserInterface;

/**
* Doctrine repository for user login records. 
*
* Responsibilities:
* - Persist new records (append-only)
* - Provide audit queries (by IP, by user, by time period)
* - Purge old logs according to a retention policy
* @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
* @package <https://github.com/Agbokoudjo/>
*/
interface LoggerLoginUserRepositoryInterface
{

    public function save(LoggerLoginUserInterface $entity, bool $flush = true): LoggerLoginUserInterface ;

    /** @return LoggerLoginUserInterface[] */
    public function findByEmail(string $email): array ;

    /** @return LoggerLoginUserInterface[] */
    public function findByUsername(string $username): array ;

    /** @return LoggerLoginUserInterface[] */
    public function findByIp(string $ip): array ;

    /** @return LoggerLoginUserInterface[] */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array ;

    public function countByEmailSince(string $email, \DateTimeInterface $since): int ;

    public function countByIpSince(string $ip, \DateTimeInterface $since): int ;

    public function findLastByEmail(string $email): ?LoggerLoginUserInterface ;

    /**
     * Purge physique (pas de SoftDeleteable sur cette entité).
     */
    public function purgeOlderThan(\DateTimeInterface $before): int ;

    public function getClassName(): string ;
}