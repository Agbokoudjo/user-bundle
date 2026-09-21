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

namespace Wlindabla\UserBundle\Domain\User\Service;

use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Domain\User\Service\CanonicalFieldsUpdaterInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class CanonicalFieldsUpdater implements CanonicalFieldsUpdaterInterface{
    
    public function updateCanonicalFields(BaseUserInterface $user): void
    {
        $user->setUsernameCanonical($this->canonicalizeUsername($user->getUsername()));
        $user->setEmailCanonical($this->canonicalizeEmail($user->getEmail()));
    }

    public function canonicalizeEmail(?string $email): ?string
    {
        return $this->canonicalize($email);
    }

    public function canonicalizeUsername(?string $username): ?string
    {
        return $this->canonicalize($username);
    }

    public function canonicalize(?string $string): ?string
    {
        if (null === $string) {
            return null;
        }

        $detectedOrder = mb_detect_order();
        \assert(\is_array($detectedOrder));

        $encoding = mb_detect_encoding($string, $detectedOrder, true);

        return false !== $encoding
            ? mb_convert_case($string, \MB_CASE_LOWER, $encoding)
            : mb_convert_case($string, \MB_CASE_LOWER);
    }
}
