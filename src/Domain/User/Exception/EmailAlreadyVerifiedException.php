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

namespace Wlindabla\UserBundle\Domain\User\Exception;

use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;


/**
 * Exception lancée quand on tente de vérifier un email déjà vérifié.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Domain\User\Exception
 */
final class EmailAlreadyVerifiedException extends InvalidTokenException
{
    public function __construct(
        string $message = 'Cet email est déjà vérifié.',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, self::CODE_ALREADY_USED, $previous);
    }

    public static function forUser(string $email): self
    {
        return new self(
            sprintf('L\'email de l\'utilisateur %s est déjà vérifié. Vous pouvez vous connecter.', $email)
        );
    }
}
