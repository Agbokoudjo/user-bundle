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

namespace Wlindabla\UserBundle\Infrastructure\Persistance;

use Wlindabla\UserBundle\Domain\LoggerLoginUser\Application\Manager\LoggerLoginUserManagerInterface;
use Wlindabla\UserBundle\Domain\LoggerLoginUser\Model\LoggerLoginUserInterface;
use Wlindabla\UserBundle\Domain\LoggerLoginUser\Model\Persistance\LoggerLoginUserRepositoryInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Implémentation Doctrine du contrat de persistance des logs de connexion.
 *
 * Fait le pont entre le domaine (LoggerLoginUserInterface) et l'infrastructure
 * Doctrine (LoggerLoginUserRepository), à l'image de UserManager/UserSessionManager.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class LoggerLoginUserManager implements LoggerLoginUserManagerInterface
{
    public function __construct(
        private readonly LoggerLoginUserRepositoryInterface $repository,
        private readonly UserManagerInterface $userManager
    ) {}

    public function save(LoggerLoginUserInterface $entity, bool $flush = true): LoggerLoginUserInterface
    {
        if (!$entity instanceof LoggerLoginUserInterface) {
            throw new \InvalidArgumentException(\sprintf(
                'Expected instance of %s, got %s.',
                LoggerLoginUserInterface::class,
                get_debug_type($entity)
            ));
        }

        /**
         * @var BaseUserInterface|null $user
         */
        $user =$this->userManager->findOneByUsernameOrEmail($entity->getUsername(),$entity->getEmail()); 
        $user->setLastLogin(new \DateTime("now", new \DateTimeZone("UTC")));
        $this->userManager->save($user,true);

        return $this->repository->save($entity, $flush);
    }

    public function findByEmail(string $email): array
    {
        return $this->repository->findByEmail($email);
    }

    public function findByUsername(string $username): array
    {
        return $this->repository->findByUsername($username);
    }

    public function findByIp(string $ip): array
    {
        return $this->repository->findByIp($ip);
    }

    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->repository->findByDateRange($from, $to);
    }

    public function countByEmailSince(string $email, \DateTimeInterface $since): int
    {
        return $this->repository->countByEmailSince($email, $since);
    }

    public function countByIpSince(string $ip, \DateTimeInterface $since): int
    {
        return $this->repository->countByIpSince($ip, $since);
    }

    public function findLastByEmail(string $email): ?LoggerLoginUserInterface
    {
        return $this->repository->findLastByEmail($email);
    }

    public function purgeOlderThan(\DateTimeInterface $before): int
    {
        return $this->repository->purgeOlderThan($before);
    }

    public function createAndSave(
        string $username,
        string $email,
        string $lastLoginIp,
        string $rawUserAgent,
        string $userAgentLabel,
        \DateTimeInterface $createdAt
    ):void {
        /**
         * @var LoggerLoginUserInterface
         */
         $loggerLoginUser = new ($this->repository->getClassName())(); 

            $loggerLoginUser
                ->setUsername($username)
                ->setEmail($email)
                ->setLastLoginIp($lastLoginIp)
                ->setUserAgent($rawUserAgent)
                ->setUserAgentLabel($userAgentLabel)
                ->setCreatedAt($createdAt) ;

        $this->save($loggerLoginUser,true) ;
    }
}