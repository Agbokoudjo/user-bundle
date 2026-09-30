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

use Wlindabla\ShareServiceBundle\Bus\EventBusInterface;
use Wlindabla\ShareServiceBundle\Security\Generator\GenerateTemporaryPasswordService;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Enum\AccountStatus;
use Wlindabla\UserBundle\Domain\User\Event\ToggleUserAccountEvent;

/**
 * Use case for activating or deactivating a user account.
 *
 * Responsibilities:
 * - find the user by ID, username or email
 * - generate a new password on activation, if needed
 * - update the account status
 * - dispatch a domain event for notifications
 *
 * Note: a password is only generated on ACTIVATION, never on deactivation.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class ToggleUserAccountHandler
{
    public function __construct(
        private readonly UserManagerInterface $userManager,
        private readonly EventBusInterface $eventBus,
        private readonly GenerateTemporaryPasswordService $generateTemporaryPassword,
    ) {
    }

    public function handle(
        string|int $userIdOrUsername,
        AccountStatus $status = AccountStatus::ACTIVE,
    ): void {
        if (is_int($userIdOrUsername) || ctype_digit($userIdOrUsername)) {
            $user = $this->userManager->find((int) $userIdOrUsername);
        } else {
            $user = $this->userManager->findUserByUsernameOrEmail($userIdOrUsername);
        }

        if (null === $user) {
            return;
        }

        if ($user->isEnabled() === $status->toBool()) {
            return;
        }

        $plainPasswordTemporary = null;

        // First activation (no password hash yet): generate one. An
        // existing password is always kept as-is, including on
        // reactivation.
        if (AccountStatus::ACTIVE === $status && !$user->hasPassword()) {
            $plainPasswordTemporary = $this->generateTemporaryPassword->generateTemporaryPassword();
            $user->setPlainPassword($plainPasswordTemporary);
        }

        $user->setEnabled($status->toBool());
        $this->userManager->updatePassword($user);
        $user->setUpdatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
        $this->userManager->save($user);

        $this->eventBus->dispatch(new ToggleUserAccountEvent($user, $plainPasswordTemporary));
    }
}