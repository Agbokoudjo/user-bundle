<?php

declare(strict_types=1);

namespace Wlindabla\UserBundle\Domain\LoggerLoginUser\Application\Manager;

use DateTimeInterface;
use Wlindabla\UserBundle\Domain\LoggerLoginUser\Model\LoggerLoginUserInterface;

/**
 * Contrat de persistance pour les enregistrements de connexion utilisateur.
 *
 * Implémenté côté Infrastructure (Doctrine). Le LoginSubscriber dépend
 * uniquement de ce contrat, jamais de l'implémentation concrète.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
interface LoggerLoginUserManagerInterface
{
    public function save(LoggerLoginUserInterface $entity, bool $flush = true): LoggerLoginUserInterface;

    /** @return LoggerLoginUserInterface[] */
    public function findByEmail(string $email): array;

    public function createAndSave(
        string $username,
        string $email,
        string $lastLoginIp,
        string $rawUserAgent,
        string $userAgentLabel,
        \DateTimeInterface $createdAt
    ):void ;

    /** @return LoggerLoginUserInterface[] */
    public function findByUsername(string $username): array;

    /** @return LoggerLoginUserInterface[] */
    public function findByIp(string $ip): array;

    /** @return LoggerLoginUserInterface[] */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array;

    public function countByEmailSince(string $email, \DateTimeInterface $since): int;

    public function countByIpSince(string $ip, \DateTimeInterface $since): int;

    public function findLastByEmail(string $email): ?LoggerLoginUserInterface;

    public function purgeOlderThan(\DateTimeInterface $before): int;
}