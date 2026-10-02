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

namespace Wlindabla\UserBundle\Tests\Infrastructure\Security\Authorization;

use PHPUnit\Framework\TestCase;
use Wlindabla\UserBundle\Domain\Permission\Model\Persistance\UserPermissionGroupRepositoryInterface;
use Wlindabla\UserBundle\Domain\Permission\Model\Persistance\UserPermissionRoleRepositoryInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Infrastructure\Security\Authorization\AuthorizationCheckerForUser;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class AuthorizationCheckerForUserTest extends TestCase
{
    public function testGrantedImmediatelyWhenTheSymfonyRoleMatches(): void
    {
        $user = $this->createMock(BaseUserInterface::class);
        $user->method('hasRole')->with('ROLE_ADMIN')->willReturn(true);

        $roleRepo = $this->createMock(UserPermissionRoleRepositoryInterface::class);
        $roleRepo->expects(self::never())->method('userHasPermission');

        $checker = new AuthorizationCheckerForUser(
            $roleRepo,
            $this->createMock(UserPermissionGroupRepositoryInterface::class),
        );

        self::assertTrue($checker->isGrantedForUser($user, 'ROLE_ADMIN'));
    }

    public function testFallsBackToDirectPermissionGrant(): void
    {
        $user = $this->createMock(BaseUserInterface::class);
        $user->method('hasRole')->willReturn(false);
        $user->method('getId')->willReturn(42);

        $roleRepo = $this->createMock(UserPermissionRoleRepositoryInterface::class);
        $roleRepo->method('userHasPermission')->with(42, 'invoices:approve')->willReturn(true);

        $groupRepo = $this->createMock(UserPermissionGroupRepositoryInterface::class);
        $groupRepo->expects(self::never())->method('userHasPermissionViaGroup');

        $checker = new AuthorizationCheckerForUser($roleRepo, $groupRepo);

        self::assertTrue($checker->isGrantedForUser($user, 'invoices:approve'));
    }

    public function testFallsBackToGroupInheritedPermissionWhenNoDirectGrant(): void
    {
        $user = $this->createMock(BaseUserInterface::class);
        $user->method('hasRole')->willReturn(false);
        $user->method('getId')->willReturn('uuid-123');

        $roleRepo = $this->createMock(UserPermissionRoleRepositoryInterface::class);
        $roleRepo->method('userHasPermission')->willReturn(false);

        $groupRepo = $this->createMock(UserPermissionGroupRepositoryInterface::class);
        $groupRepo->method('userHasPermissionViaGroup')->with('uuid-123', 'invoices:approve')->willReturn(true);

        $checker = new AuthorizationCheckerForUser($roleRepo, $groupRepo);

        self::assertTrue($checker->isGrantedForUser($user, 'invoices:approve'));
    }

    public function testDeniedWhenTheUserHasNoId(): void
    {
        $user = $this->createMock(BaseUserInterface::class);
        $user->method('hasRole')->willReturn(false);
        $user->method('getId')->willReturn(null);

        $roleRepo = $this->createMock(UserPermissionRoleRepositoryInterface::class);
        $roleRepo->expects(self::never())->method('userHasPermission');

        $checker = new AuthorizationCheckerForUser(
            $roleRepo,
            $this->createMock(UserPermissionGroupRepositoryInterface::class),
        );

        self::assertFalse($checker->isGrantedForUser($user, 'invoices:approve'));
    }

    public function testIsMemberOfGroupDelegatesToTheGroupRepository(): void
    {
        $user = $this->createMock(BaseUserInterface::class);
        $user->method('getId')->willReturn(7);

        $groupRepo = $this->createMock(UserPermissionGroupRepositoryInterface::class);
        $groupRepo->method('userHasGroup')->with(7, 'billing_manager')->willReturn(true);

        $checker = new AuthorizationCheckerForUser(
            $this->createMock(UserPermissionRoleRepositoryInterface::class),
            $groupRepo,
        );

        self::assertTrue($checker->isMemberOfGroupForUser($user, 'billing_manager'));
    }
}