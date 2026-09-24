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

namespace App\UI\Http\EventSubscriber;

use App\Infrastructure\Doctrine\Entity\SonataUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * DossierScopeFilterActivationListener — active `DossierScopeFilter` à
 * chaque requête HTTP (jamais pour les commandes CLI/workers, cf. docblock
 * du filtre) et y injecte l'id de la personne connectée.
 *
 * ⚠️ N'active RIEN si personne n'est authentifiée — dans ce cas le filtre
 * reste inactif (cf. le comportement documenté dans DossierScopeFilter :
 * pas de paramètre = pas de restriction appliquée par CE filtre-là ; ne pas
 * compter dessus pour protéger un accès anonyme, c'est le rôle du firewall
 * Symfony, pas de ce filtre).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsEventListener(event: 'kernel.request', priority: 6)]
final class DossierScopeFilterActivationListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof SonataUser || null === $user->getId()) {
            return;
        }

        // Le Directeur voit l'intégralité des dossiers, sans restriction de
        // portée — supervision métier globale, distincte du rôle technique
        // ROLE_SUPER_ADMIN (comptes/sécurité système, cf. échange du 27/07).
        if ($user->hasRole('ROLE_DIRECTOR')) {
            return;
        }

        $filter = $this->entityManager->getFilters()->enable('dossier_scope');
        $filter->setParameter('current_user_id', $user->getId());
    }
}
