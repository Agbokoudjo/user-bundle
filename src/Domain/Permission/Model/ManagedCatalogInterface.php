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

namespace Wlindabla\UserBundle\Domain\Permission\Model;

use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Implemented by any catalog entity (PermissionRoleInterface,
 * PermissionGroupInterface, or any future entity of the same kind) whose
 * lifecycle tracks who created it and who currently manages it — so a
 * single authorization Voter can protect every such entity without
 * duplicating logic per entity type.
 *
 * Typed against this bundle's own BaseUserInterface, never a concrete
 * project entity — any project's User class works, as with every other
 * contract in this bundle.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface ManagedCatalogInterface
{
    public function getCreatedBy(): ?BaseUserInterface;

    public function getCurrentManager(): ?BaseUserInterface;

    public function setCurrentManager(BaseUserInterface $currentManager): static;
}