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

namespace Wlindabla\UserBundle\Infrastructure\Security\Authorization;

use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * AuthorizationCheckerForUserInterface — point d'entrée UNIQUE pour vérifier
 * si une personne détient un droit, que ce droit soit un rôle technique
 * Symfony (BaseUserInterfaceUser::roles) OU un droit métier attribué via PermissionRole/
 * UserPermissionRole (Planche 01). Les Voters ne doivent JAMAIS interroger
 * UserPermissionRoleRepository ou UserPermissionGroupRepository directement — toujours passer par ici.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface AuthorizationCheckerForUserInterface
{
    public function isGrantedForUser(BaseUserInterface $user, string $role): bool;

    /**
     * Appartenance à un PermissionGroup, par son code.
     * Complète isGrantedForUser(), qui répond sur un DROIT et non sur un groupe.
     */
    public function isMemberOfGroupForUser(BaseUserInterface $user, string $groupCode): bool;
}
