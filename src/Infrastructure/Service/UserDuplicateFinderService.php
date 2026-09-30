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

namespace Wlindabla\UserBundle\Infrastructure\Service;

use Wlindabla\ShareServiceBundle\Similarity\IdentitySimilarityChecker;
use Wlindabla\UserBundle\Domain\User\Model\Persistance\UserFindDuplicatesRepositoryInterface;

/**
 * Détecte qu'un username soumis (création manuelle d'un compte admin,
 * ou approbation d'une AccountRequest) ressemble fortement à un compte
 * SonataUser déjà existant.
 *
 * pré-sélection indexée (pg_trgm) puis confirmation fine (IdentitySimilarityChecker).
 */
final class UserDuplicateFinderService
{
    public function __construct(
        private readonly UserFindDuplicatesRepositoryInterface $userRepository,
        private readonly IdentitySimilarityChecker $identitySimilarityChecker,
    ) {
    }

    /**
     * @return array<int, array{id: int, username: string, trigramScore: float}>
     */
    public function findDuplicates(string $submittedUsername, int $candidateLimit = 10): array
    {
        $candidates = $this->userRepository->findSimilarCandidates(
            $submittedUsername,
            $candidateLimit,
        );

        $duplicates = [];

        foreach ($candidates as $candidate) {
            $isDuplicate = $this->identitySimilarityChecker->hasHighSimilarity(
                $submittedUsername,
                $candidate['username'],
            );

            if ($isDuplicate) {
                $duplicates[] = [
                    'id' => (int) $candidate['id'],
                    'username' => $candidate['username'],
                    'trigramScore' => (float) $candidate['score'],
                ];
            }
        }

        return $duplicates;
    }

    public function hasSuspectedDuplicate(string $submittedUsername, int $candidateLimit = 10): bool
    {
        return [] !== $this->findDuplicates($submittedUsername, $candidateLimit);
    }
}
