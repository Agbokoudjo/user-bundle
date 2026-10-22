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

namespace Wlindabla\UserBundle\Domain\EmailChange\Model\Persistance;

use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeRequestInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeValidationInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface EmailChangeValidationRepositoryInterface
{
    /**
     * @return EmailChangeValidationInterface[]
     */
    public function findByRequest(EmailChangeRequestInterface $request): array ;
    
    public function save(EmailChangeValidationInterface $validation, bool $flush = true): void ;

    public function getClassName(): string ;


    /**
     * Complements the UNIQUE constraint on the concrete table: that
     * constraint is the real guarantee against concurrent double votes,
     * this check only turns it into a clear error message instead of a
     * raw SQL exception.
     */
    public function hasAlreadyVoted(EmailChangeRequestInterface $request, BaseUserInterface $validator): bool ;

}