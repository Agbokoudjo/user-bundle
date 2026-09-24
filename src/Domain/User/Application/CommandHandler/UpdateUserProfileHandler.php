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

namespace Wlindabla\UserBundle\Domain\User\Application\CommandHandler;

use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Service\CanonicalFieldsUpdaterInterface;

/**
 * cette class est appeller par les evenement de sonataAdmin lorsque le projet utilise sonatAdmin comme dependances
 * il est aussi appeller par les nevenements de doctrine dont les actions s'effectue or de l'ecran de sonataAdmin puique cet dernier ecrase les evenement 
 * de doctrine par ses hooks comportements observer sur plisueurs projet
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final readonly class UpdateUserProfileHandler
{
    public function __construct(
        private UserManagerInterface $userManager,
        private CanonicalFieldsUpdaterInterface $canonicalFields
    ) {}

    public function handle(string|int $id): void
    {
        $user = $this->userManager->find($id);
        if (null === $user) {
            return;
        }

        $this->canonicalFields->updateCanonicalFields($user);

        // Slug généré UNE SEULE FOIS, jamais régénéré — l'ancien code utilisait
        // time() comme différenciateur, garantissant un slug différent à CHAQUE
        // appel, donc un changeset Doctrine permanent → postUpdate rediclenché
        // en boucle par DoctrineUserEventListener (diagnostiqué le 28/07) sur plusieur projets.
        if (null === $user->getSlug()) {
            $slugHash = md5(\sprintf('%s_%d', $user->getUsernameCanonical(), $user->getId()));
            $user->setSlug(sha1($slugHash));
        }

        $this->userManager->updatePassword($user);
        $this->userManager->save($user);
    }
}
