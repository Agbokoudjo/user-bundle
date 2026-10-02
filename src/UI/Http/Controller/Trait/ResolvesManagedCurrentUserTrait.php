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

namespace Wlindabla\UserBundle\UI\Http\Controller\Trait;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;

/**
 * Reloads the current user ($this->getUser()) as a Doctrine entity
 * TRULY MANAGED by the current request's EntityManager.
 *
 * ⚠️ WHY THIS TRAIT EXISTS — never persist or associate $this->getUser() directly:
 *
 * UserProvider::refreshUser() (called by Symfony Security on EVERY request
 * to rebuild the user from the session token) deserializes the user from a JSON cache.
 * This instantiates a brand new PHP object that has never been loaded by or registered
 * with the current request's EntityManager. Although this object carries a valid identifier
 * and up-to-date data, Doctrine does not recognize it in its identity map.
 *
 * Consequence if persisted or associated as-is with another entity
 * (e.g., EmailChangeRequest::$requestedBy, EmailChangeValidation::$validator):
 * Doctrine treats it as a NEW entity and attempts a RE-INSERTION using the existing ID.
 * This was observed during development testing on 08/20/2024 as a
 * NotNullConstraintViolationException (phone column), which is a clear symptom
 * of an INSERT query being executed where an UPDATE or association was expected.
 *
 * Suitable for use in any controller hierarchy exposing `getUser()`
 * (Symfony\Bundle\FrameworkBundle\Controller\AbstractController and,
 * through inheritance, Sonata\AdminBundle\Controller\CRUDController).
 * Intentionally designed as a trait rather than a common base class, since
 * TwoFactorAuthController and WlindablaAdminCRUDController do not share a common
 * ancestor other than Symfony itself.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
trait ResolvesManagedCurrentUserTrait
{
    /**
     * @throws AccessDeniedException If no user is authenticated, or if reloading the
     *                                 user via find() fails (e.g., account deleted in
     *                                 the meantime, cache inconsistency, etc.).
     */
    protected function resolveManagedCurrentUser(UserManagerInterface $userManager, UserInterface$currentUser): UserInterface
    {
        if (!$currentUser instanceof UserInterface || null ===$currentUser->getId()) {
            throw new AccessDeniedException('Current user not found or invalid.');
        }

        $managedUser = $userManager->find($currentUser->getId());

        if (!$managedUser instanceof UserInterface) {
            throw new AccessDeniedException('Current user not found or invalid.');
        }

        return $managedUser;
    }
}