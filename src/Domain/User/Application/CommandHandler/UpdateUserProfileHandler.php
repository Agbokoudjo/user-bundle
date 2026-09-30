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

/**
 * Called by SonataAdmin's own persistence events when the project uses
 * SonataAdmin, and also by raw Doctrine events for changes made outside
 * the SonataAdmin screens — SonataAdmin's hooks have been observed, on
 * several projects, to override Doctrine's own lifecycle events when its
 * screens are used.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final readonly class UpdateUserProfileHandler
{
    public function __construct(
        private UserManagerInterface $userManager
    ) {
    }

    public function handle(string|int $id): void
    {
        $user = $this->userManager->find($id);

        if (null === $user) {
            return;
        }

        // Slug generated ONCE, never regenerated — the old code used
        // time() as a differentiator, guaranteeing a different slug on
        // EVERY call, hence a permanent Doctrine changeset → postUpdate
        // re-triggered in a loop by DoctrineUserEventListener (diagnosed
        // 2026-07-28, across several projects).
        if (null === $user->getSlug()) {
            $slugHash = md5(\sprintf('%s_%d', $user->getUsernameCanonical(), $user->getId()));
            $user->setSlug(sha1($slugHash));
        }

        $this->userManager->save($user,true);
    }
}