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

use App\Infrastructure\Doctrine\Entity\SonataUser;
use App\Infrastructure\Security\Provider\UserProvider;
use Sonata\AdminBundle\Event\PersistenceEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\UpdateUserProfileHandler;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Domain\User\Service\UpdatePasswordUserServiceInterface;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\CacheUserProviderInterface;

/**
 * Event Subscriber pour les événements sonataAdmin des utilisateurs dans l'ecran de sonataAdmin
 * 
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final  class SoanatUserEventSubscriber implements EventSubscriberInterface
{
    /**
     * Délai en millisecondes avant l'exécution de l'async UpdatePasswordUserHandler .
     * Laisse le temps à Sonata de finaliser son flush + commit BDD.
     * 45 secondes = largement suffisant dans tous les cas.
     */
    private const float UPDATE_PASSWORD_SYNC_DELAY_MS = 45000;

    public function __construct(
        private readonly AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        private readonly CacheUserProviderInterface $userProvider
        ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'sonata.admin.event.persistence.post_update' => ['postUpdate',500],
            'sonata.admin.event.persistence.pre_update' => ['preUpdate', 500],
            'sonata.admin.event.persistence.post_persist'=>['postPersist',500],
          
        ];
    }

    final public function postPersist(PersistenceEvent $sonataEvent): void
    {
        $entity = $sonataEvent->getObject();
        if (!$entity instanceof BaseUserInterface) return;

        $this->dispatchUpdateCommand($entity);
    }

    final public function preUpdate(PersistenceEvent $event):void {

        $adminUser=$event->getObject() ;
        if (!($adminUser instanceof BaseUserInterface)) return;

        $plainPassword = $adminUser->getPlainPassword();
        if ($plainPassword) {
            $this->asyncMethodDispatcher->dispatch(
                UpdatePasswordUserServiceInterface::class,
                'handle',
                [
                    $adminUser->getId(),
                    $plainPassword
                ],
                new \DateTimeImmutable(
                    sprintf('+%d milliseconds', self::UPDATE_PASSWORD_SYNC_DELAY_MS),
                    new \DateTimeZone('UTC')
                )
            );
        }
    }

    final public function postUpdate(PersistenceEvent $sonataEvent): void
    {
        $entity=$sonataEvent->getObject();
        if (!$entity instanceof BaseUserInterface) return;

        $this->dispatchUpdateCommand($entity);
        $this->userProvider->invalidateUserCache($entity->getId());
    }
    
    private function dispatchUpdateCommand(BaseUserInterface $entity): void
    {
        $this->asyncMethodDispatcher->dispatch(UpdateUserProfileHandler::class, 'handle', [$entity->getId()]);
    }
}
