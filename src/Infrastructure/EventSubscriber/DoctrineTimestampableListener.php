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

namespace App\Infrastructure\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsDoctrineListener(event: Events::prePersist, priority: 100)]
#[AsDoctrineListener(event: Events::preUpdate, priority: 100)]
final class DoctrineTimestampableListener
{
    public function __construct() {}

    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        if (method_exists($object, 'getCreatedAt') && $object->getCreatedAt() === null) {
            $object->setCreatedAt(new \DateTimeImmutable("now", new \DateTimeZone("UTC")));
        }
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        
        if (method_exists($object, 'setUpdatedAt')) {
            $object->setUpdatedAt(new \DateTime("now", new \DateTimeZone("UTC")));
        }
    }

    
}
