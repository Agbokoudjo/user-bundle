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

namespace Wlindabla\UserBundle\Domain\User\Application\Manager;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface UserFindDuplicatesRepositoryInterface
{
    /**
     * Pré-sélectionne, via l'index GIN pg_trgm, les comptes SonataUser
     * existants dont le username ressemble le plus à l'identité soumise.
     *
     * - opérateur `%` (seul indexable par le GIN pg_trgm), pas la fonction
     *   similarity() directement dans le WHERE ;
     * - set_config() plutôt que SET LOCAL, pour accepter un paramètre bindé ;
     * - la requête complète est englobée dans une transaction explicite
     *   pour que set_config(..., true) (portée LOCAL) s'applique bien au
     *   SELECT qui suit, quel que soit le contexte transactionnel appelant.
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