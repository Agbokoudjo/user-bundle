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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\UpdateUserProfileHandler;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface ;
use Wlindabla\UserBundle\Domain\User\Service\UpdatePasswordUserServiceInterface;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\CacheUserProviderInterface;

/**
 * cet listener ecoute les event de dodfctrine pour de declencher cette action
 * il est appeler uniquement lors de de la creation d'un utilisateur en ligne de commande symfony ou lors de l'inscription d'utilisateur sans l'ecran de sonataAdmin
 * 
 * car lorsque en utilisant la dependances de sonataAdmin dans les projets recent on a constater les listenerer de doctrine sont netraliser 
 * par les evenements de hooks de sonataAdmin raison pour laquelle on ecrire cettte classe 
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsDoctrineListener(event: Events::postPersist, priority: 100)]
#[AsDoctrineListener(event: Events::postUpdate, priority: 100)]
#[AsDoctrineListener(event: Events::preUpdate, priority: 100)]
final class DoctrineUserEventListener
{
   /**
     * Délai en millisecondes avant l'exécution de l'async UpdatePasswordUserHandler .
     * Laisse le temps à Sonata de finaliser son flush + commit BDD.
     * 45 secondes = largement suffisant dans tous les cas.
     */
    private const int UPDATE_PASSWORD_SYNC_DELAY_MS = 45000;

    public function __construct(
        private readonly AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        private readonly CacheUserProviderInterface $userProvider,
        private readonly EntityManagerInterface $entityManager
        ) {}

    final public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof BaseUserInterface) return;

        $this->dispatchUpdateCommand($entity);
    }

    final public function preUpdate(LifecycleEventArgs $args):void {

        $adminUser=$args->getObject() ;
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
                    \sprintf('+%d milliseconds', self::UPDATE_PASSWORD_SYNC_DELAY_MS),
                    new \DateTimeZone('UTC')
                )
            );
        }
    }

    final public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity=$args->getObject();
        if (!$entity instanceof BaseUserInterface) return;


        // Ne redéclenche UpdateUserProfileHandler QUE si username/email ont
        // RÉELLEMENT changé — pas sur chaque update de l'entité (token,
        // mot de passe, slug...). C'est ce filtre qui manquait : la boucle
        // venait de SecureTokenService::generateEmailConfirmationToken()
        // (confirmationToken/tokenRequestedAt changent à chaque appel,
        // sans rapport avec les champs canoniques) redéclenchant ce même
        // handler en cascade, diagnostiqué le 28/07.
        $changeSet = $this->entityManager->getUnitOfWork()->getEntityChangeSet($entity);
        if (!isset($changeSet['username']) && !isset($changeSet['email'])) {
            return;
        }

        $this->dispatchUpdateCommand($entity);
        $this->userProvider->invalidateUserCache($entity->getId());
    }
    
    private function dispatchUpdateCommand(BaseUserInterface $entity): void
    {
        
        $this->asyncMethodDispatcher->dispatch(UpdateUserProfileHandler::class, 'handle', [$entity->getId()]);
    }
}
