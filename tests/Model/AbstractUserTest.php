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

namespace Wlindabla\UserBundle\Tests\Model;

use PHPUnit\Framework\TestCase;
use Wlindabla\UserBundle\Domain\User\Model\AbstractBaseUser;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class AbstractUserTest extends TestCase
{
    private function makeUser(): AbstractBaseUser
    {
        return new class extends AbstractBaseUser {
        };
    }

    public function testEveryUserHasOnlyTheDefaultRole(): void
    {
        self::assertSame(['ROLE_USER'], $this->makeUser()->getRoles());
    }

    public function testDefaultRoleIsNeverAdmin(): void
    {
        self::assertFalse($this->makeUser()->hasRole('ROLE_ADMIN'));
    }

    public function testRolesAreNormalizedAndDeduplicated(): void
    {
        $user = $this->makeUser()->setRoles(['role_admin', 'ROLE_ADMIN', 'ROLE_USER']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testRemoveRoleReindexes(): void
    {
        $user = $this->makeUser()->setRoles(['ROLE_A', 'ROLE_B'])->removeRole('ROLE_A');

        self::assertSame(['ROLE_B', 'ROLE_USER'], $user->getRoles());
    }

    public function testToStringNeverFailsWithoutEmail(): void
    {
        self::assertSame('', (string) $this->makeUser());
    }
}