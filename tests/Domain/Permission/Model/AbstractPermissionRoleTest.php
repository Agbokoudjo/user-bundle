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

namespace Wlindabla\UserBundle\Tests\Domain\Permission\Model;

use PHPUnit\Framework\TestCase;
use Wlindabla\UserBundle\Domain\Permission\Model\AbstractPermissionRole;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class AbstractPermissionRoleTest extends TestCase
{
    private function makePermission(): AbstractPermissionRole
    {
        return new class extends AbstractPermissionRole {
        };
    }

    public function testRevokeSetsDeletedAtDeletedByAndReason(): void
    {
        $permission = $this->makePermission();
        $revokedBy = $this->createMock(BaseUserInterface::class);

        $permission->revoke($revokedBy, 'No longer needed');

        self::assertNotNull($permission->getDeletedAt());
        self::assertSame($revokedBy, $permission->getDeletedBy());
        self::assertSame('No longer needed', $permission->getDeletedReason());
    }

    public function testRevokeAcceptsANullReason(): void
    {
        $permission = $this->makePermission();

        $permission->revoke($this->createMock(BaseUserInterface::class));

        self::assertNull($permission->getDeletedReason());
    }

    public function testToStringReturnsTheName(): void
    {
        $permission = $this->makePermission()->setName('invoices:approve');

        self::assertSame('invoices:approve', (string) $permission);
    }
}