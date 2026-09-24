<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Infrastructure\Doctrine\Entity\SonataUser;
use App\Infrastructure\Persistance\UserManager;
use Sonata\AdminBundle\Event\PersistenceEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SonataBlameableSubscriber implements EventSubscriberInterface
{
  public function __construct(
        private Security $security,
        private readonly UserManager $userManager
    ) {}
    public static function getSubscribedEvents(): array
    {
        return [
            // On met une priorité de 10 pour qu'il s'exécute AVANT le Timestampable
            'sonata.admin.event.persistence.pre_persist' => ['onPrePersist', 10],
            'sonata.admin.event.persistence.pre_update'  => ['onPreUpdate', 10],
        ];
    }

    public function onPrePersist(PersistenceEvent $event): void
    {
        $object = $event->getObject();
        $rawUser = $this->security->getUser();

        if ($rawUser instanceof SonataUser) {
            $user = $this->userManager->getReference($rawUser->getId());
            if (method_exists($object, 'getCreatedBy') && $object->getCreatedBy() === null) {
                $object->setCreatedBy($user);
            }

            if (method_exists($object, 'getCurrentManager') && $object->getCurrentManager() === null) {
                $object->setCurrentManager($user);
            }

            if (method_exists($object, 'setUpdatedBy')) {
                $object->setUpdatedBy($user);
            }
        }
    }

    public function onPreUpdate(PersistenceEvent $event): void
    {
        $object = $event->getObject();
        $rawUser = $this->security->getUser();

        if ($rawUser instanceof SonataUser) {
            $user = $this->userManager->getReference($rawUser->getId());

            if (method_exists($object, 'setUpdatedBy')) {
                $object->setUpdatedBy($user);
            }
        }
    }
}
