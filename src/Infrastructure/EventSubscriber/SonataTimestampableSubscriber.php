<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use Sonata\AdminBundle\Event\PersistenceEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SonataTimestampableSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Déclenché juste avant l'insertion en Base de Données par Sonata
            'sonata.admin.event.persistence.pre_persist' => ['onPrePersist', 11],
            // Déclenché juste avant la mise à jour en Base de Données par Sonata
            'sonata.admin.event.persistence.pre_update'  => ['onPreUpdate', 11],
        ];
    }

    /**
     * Gestion du "createdAt" et "updatedAt" à la création de l'objet
     */
    public function onPrePersist(PersistenceEvent $event): void
    {
        $object = $event->getObject();

        if (method_exists($object, 'getCreatedAt') && $object->getCreatedAt() === null) {
            $object->setCreatedAt(new \DateTimeImmutable("now", new \DateTimeZone("UTC")));
        }
    }

    /**
     * Gestion du "updatedAt" lors de la modification de l'objet
     */
    public function onPreUpdate(PersistenceEvent $event): void
    {
        $object = $event->getObject();

        if (method_exists($object, 'setUpdatedAt')) {
            $object->setUpdatedAt(new \DateTime("now", new \DateTimeZone("UTC")));
        }
    }
}
