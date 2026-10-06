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
use Wlindabla\ShareServiceBundle\Similarity\IdentityNormalizer;
use Wlindabla\UserBundle\Infrastructure\Model\UserIdentityNormalizerInterface;

/**
* Recalculates identity_normalized whenever a User is saved, based
* on their username (which serves as the full identity—surname + first name—
* for administrator accounts). The entity remains pure; the listener
* handles the connection to the normalization service. 
*
* @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
* @package <https://github.com/Agbokoudjo/>
*/
#[AsDoctrineListener(event: Events::prePersist, priority: 90)]
#[AsDoctrineListener(event: Events::preUpdate, priority: 90)]
final class SonataUserIdentityNormalizerDoctrineLifecycleListener
{
    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->refreshIdentityNormalized($args);
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->refreshIdentityNormalized($args);
    }

    private function refreshIdentityNormalized(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof UserIdentityNormalizerInterface) {
            $object->setIdentityNormalized(
                IdentityNormalizer::normalize($object->getUsername() ?? '')
            );
        }
    }
}
