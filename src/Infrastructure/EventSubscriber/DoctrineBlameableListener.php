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

namespace Wlindabla\UserBundle\Infrastructure\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Wlindabla\UserBundle\Domain\Permission\Model\ManagedCatalogInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Infrastructure\Persistance\UserManagerDoctrineReference;
use Wlindabla\UserBundle\Infrastructure\Security\SecurityContextInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsDoctrineListener(event: Events::prePersist, priority: 100)]
#[AsDoctrineListener(event: Events::preUpdate, priority: 100)]
final class DoctrineBlameableListener
{
    public function __construct(
        private readonly SecurityContextInterface $securityContext,
        private readonly UserManagerDoctrineReference $userManager
    ) {}

    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        $rawUser = $this->securityContext->getCurrentUser();

        if ($rawUser instanceof BaseUserInterface) {
            /**
             * @var BaseUserInterface
             */
            $user = $this->userManager->getReference($rawUser->getId());
            if (method_exists($object, 'getCreatedBy') && $object->getCreatedBy() === null) {
                $object->setCreatedBy($user);
            }

            if (method_exists($object, 'setUpdatedBy')) {
                $object->setUpdatedBy($user);
            }

            if ($object instanceof ManagedCatalogInterface && $object->getCurrentManager() === null) {
                $object->setCurrentManager($user);
            }
        }
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        $rawUser = $this->securityContext->getCurrentUser();

        if ($rawUser instanceof BaseUserInterface) {
            $user = $this->userManager->getReference($rawUser->getId());
            if (method_exists($object, 'setUpdatedBy')) {
                $object->setUpdatedBy($user);
            }
        }
    } 
}
