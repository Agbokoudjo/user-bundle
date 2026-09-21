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

namespace Wlindabla\UserBundle\Domain\User\Service;

use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

interface CanonicalFieldsUpdaterInterface
{
    public function updateCanonicalFields(BaseUserInterface $user): void;

    public function canonicalizeEmail(?string $email): ?string;

    public function canonicalizeUsername(?string $username): ?string;
}
