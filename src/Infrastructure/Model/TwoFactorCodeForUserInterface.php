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

namespace Wlindabla\UserBundle\Infrastructure\Model;

/**
 * Contract of a user managed by the bundle.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface TwoFactorCodeForUserInterface{
     /**
     * Pose les deux champs à "maintenant" (UTC) en une seule fois — appelé
     * exclusivement par TwoFactorAuthController::verify() à la validation
     * réussie d'un code.
     */
    public function markTwoFactorValidated(): void ;

    public function getTwoFactorValidatedAt(): ?\DateTimeInterface ;
    public function setTwoFactorValidatedAt(?\DateTimeInterface $twoFactorValidatedAt): void ;

    public function getTwoFactorLastCheckedAt(): ?\DateTimeInterface ;
    
    public function setTwoFactorLastCheckedAt(?\DateTimeInterface $twoFactorLastCheckedAt): void ;
}