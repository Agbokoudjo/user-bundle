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

namespace Wlindabla\UserBundle\Domain\User\Model\Persistance;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface UserFindDuplicatesRepositoryInterface
{
    /**
    * Pre-selects, using the pg_trgm GIN index, existing SonataUser accounts
    * whose username most closely resembles the submitted identity. 
    *
    * - Uses the `%` operator (the only one indexable by pg_trgm GIN)
    *   rather than the similarity() function directly in the WHERE clause; 
    * - Uses set_config() instead of SET LOCAL to allow for a bound parameter; 
    * - Wraps the entire query in an explicit transaction so that
    *   set_config(..., true) (LOCAL scope) correctly applies to the
    *   subsequent SELECT, regardless of the calling transactional context. 
    *
    * @return array<int, array{id: int, username: string, identity_normalized: string, score: float}>
    */
    public function findSimilarCandidates(
        string $rawUsername,
        int $limit = 10,
        float $minTrigramScore = 0.3,
        bool $excludeDisabled = false,
    ): array ;


    public function hasSuspectedDuplicate(string $submittedUsername, int $candidateLimit = 10): bool ;
}