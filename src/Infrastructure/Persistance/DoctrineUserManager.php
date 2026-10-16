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

use Doctrine\Persistence\ObjectRepository;
use InvalidArgumentException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Domain\User\Model\Persistance\UserRepositoryInterface;
use Wlindabla\UserBundle\Domain\User\Service\CanonicalFieldsUpdaterInterface;
use Wlindabla\UserBundle\Infrastructure\Persistance\UserManagerDoctrineReference;

/**
 * Generic Doctrine implementation of UserManagerInterface. Nothing to
 * extend: the managed entity class comes from configuration
 * (wlindabla_user.classes.user), not from subclassing.
 *
 * The repository is resolved dynamically via EntityManagerInterface, the
 * same way Doctrine's own ServiceEntityRepository resolves its manager —
 * not injected as a UserRepositoryInterface service, which would require
 * every consuming project to manually alias that interface in its own
 * services.yaml.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class DoctrineUserManager implements UserManagerInterface,UserManagerDoctrineReference
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
        private readonly CanonicalFieldsUpdaterInterface $canonicalFieldsUpdater,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    private function repository(): UserRepositoryInterface
    {
        return $this->repository ;
    }
    
    /**
     * Convenience accessor for services that don't need a full
     * EntityManager. If your repository extends
     * AbstractUserRepository, the returned object also implements
     * UserRepositoryInterface and UserFindDuplicatesRepositoryInterface —
     * check with instanceof if you need those.
     */
    public function getModelRepository(): ObjectRepository
    {
        if($this->repository instanceof ObjectRepository){
            return $this->repository ;
        }
       
        throw new InvalidArgumentException("
                Convenience accessor for services that don't need a full  EntityManager. 
                If your repository extends AbstractUserRepository, the returned object also implements
                Wlindabla\UserBundle\Domain\User\Model\Persistance\UserRepositoryInterface
                ");
        
    }

    public function updatePassword(BaseUserInterface $user): void
    {
        if (!$user instanceof PasswordAuthenticatedUserInterface) {
            throw new \InvalidArgumentException(\sprintf(
                'Expected an instance of %s, got %s.',
                PasswordAuthenticatedUserInterface::class,
                $user::class,
            ));
        }

        $plainPassword = $user->getPlainPassword();

        if (null === $plainPassword) {
            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setPlainPassword(null);
    }

    public function findUserByUsername(string $username): ?BaseUserInterface
    {
        return $this->findOneBy([
            'usernameCanonical' => $this->canonicalFieldsUpdater->canonicalizeUsername($username),
        ]);
    }

    public function findUserByEmail(string $email): ?BaseUserInterface
    {
        return $this->findOneBy([
            'emailCanonical' => $this->canonicalFieldsUpdater->canonicalizeEmail($email),
        ]);
    }

    public function findOneByUsernameOrEmail(string $username, string $email): ?BaseUserInterface{
        
         return $this->repository()->findOneByUsernameOrEmail(
                usernameCanonical :$this->canonicalFieldsUpdater->canonicalizeUsername($username),
                emailCanonical : $this->canonicalFieldsUpdater->canonicalizeEmail($email)
        );
    }

    public function findUserByUsernameOrEmail(string $usernameOrEmail): ?BaseUserInterface
    {
        if (false !== filter_var($usernameOrEmail, \FILTER_VALIDATE_EMAIL)) {
            $user = $this->findUserByEmail($usernameOrEmail);

            if (null !== $user) {
                return $user;
            }
        }

        return $this->findUserByUsername($usernameOrEmail);
    }

    public function findUserByConfirmationToken(string $token): ?BaseUserInterface
    {
        return $this->findOneBy(['confirmationToken' => $token]);
    }

    public function findUserBySlug(string $slug): ?BaseUserInterface
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function save(BaseUserInterface $entity, bool $andFlush = true): void
    {
        // Canonicalisation basique requise par l'écosystème SonataUser
        $this->canonicalFieldsUpdater->updateCanonicalFields($entity);
        $this->updatePassword($entity) ;
        $this->repository()->save($entity,$andFlush) ;
    }

    public function delete(BaseUserInterface $entity, bool $andFlush = true): void
    {
        $this->repository()->delete($entity,$andFlush) ;
    }

    public function getClass(): string
    {
        return $this->repository()->getClass();
    }

    public function findAll(): array
    {
        return $this->repository()->findAll();
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return $this->repository()->findBy($criteria, $orderBy, $limit, $offset);
    }

    public function findOneBy(array $criteria): ?BaseUserInterface
    {
        return $this->repository()->findOneBy($criteria);
    }

    public function find(mixed $id): ?BaseUserInterface
    {
        return $this->repository()->find($id);
    }

    /**
     * Requires your concrete User entity to have a no-argument
     * constructor. Override this method in your own manager if yours
     * requires constructor arguments.
     */
    public function create(): BaseUserInterface
    {
        return new ($this->getClass())();
    }

    /**
     * Doctrine's getReference() never returns null on supported ORM
     * versions — unlike find(), so this is deliberately non-nullable.
     */
    public function getReference(int|string $id): BaseUserInterface
    {
        /** @var BaseUserInterface */
        return $this->repository()->getReference($id);
    }
}