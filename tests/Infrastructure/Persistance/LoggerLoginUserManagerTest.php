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

namespace Wlindabla\UserBundle\Tests\Infrastructure\Persistance;

use PHPUnit\Framework\TestCase;
use Wlindabla\UserBundle\Domain\LoggerLoginUser\Model\LoggerLoginUserInterface;
use Wlindabla\UserBundle\Domain\LoggerLoginUser\Model\Persistance\LoggerLoginUserRepositoryInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Infrastructure\Persistance\LoggerLoginUserManager;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class LoggerLoginUserManagerTest extends TestCase
{
    public function testSaveStillPersistsTheLogWhenNoMatchingUserIsFound(): void
    {
        $entity = $this->createMock(LoggerLoginUserInterface::class);
        $entity->method('getUsername')->willReturn('ghost');
        $entity->method('getEmail')->willReturn('ghost@example.com');

        $userManager = $this->createMock(UserManagerInterface::class);
        $userManager->method('findOneByUsernameOrEmail')->willReturn(null);
        $userManager->expects(self::never())->method('save');

        $repository = $this->createMock(LoggerLoginUserRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with($entity, true)->willReturn($entity);

        $manager = new LoggerLoginUserManager($repository, $userManager);

        self::assertSame($entity, $manager->save($entity));
    }
}