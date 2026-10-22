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
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;


/**
 
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface EmailChangeRequestRepositoryInterface
{
  public function save(EmailChangeRequestInterface $request, bool $flush = true): void ;

  public function findActiveForTargetUser(BaseUserInterface $targetUser): ?EmailChangeRequestInterface ;

   /**
     * @return EmailChangeRequestInterface[]
     */
    public function findExpiredButStillActive(): array ;

     /**
     * @return EmailChangeRequestInterface[]
     */
    public function findHistoryForTargetUser(BaseUserInterface $targetUser): array ;
 
    /**
     * @phpstan-return EmailChangeRequestInterface|null
     */
    public function find(mixed $id): ?object;

    public function getClassName(): string ;

}