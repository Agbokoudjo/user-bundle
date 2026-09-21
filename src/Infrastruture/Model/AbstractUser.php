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

namespace Wlindabla\UserBundle\Infrastruture\Model;

use Wlindabla\UserBundle\Domain\User\Model\AbstractBaseUser;
use Wlindabla\UserBundle\Infrastruture\Model\UserInterface ;

/**
 * Framework-agnostic base implementation of a user.
 *
 * Persistence mapping is added by the Doctrine layer, not here.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractUser extends AbstractBaseUser implements UserInterface
{
    
}