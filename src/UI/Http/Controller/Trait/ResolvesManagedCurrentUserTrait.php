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

namespace Wlindabla\UserBundle\UI\Http\Controller\Trait;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;

/**
 * Recharge l'utilisateur courant ($this->getUser()) comme une entité
 * Doctrine RÉELLEMENT GÉRÉE par l'EntityManager de la requête en cours.
 *
 * ⚠️ POURQUOI CE TRAIT EXISTE — ne jamais persister/associer $this->getUser()
 * directement :
 *
 * UserProvider::refreshUser() (appelé par Symfony Security sur CHAQUE
 * requête pour reconstruire l'utilisateur depuis le token de session)
 * désérialise l'utilisateur depuis un cache JSON — ce qui fabrique un objet
 * PHP tout neuf, jamais chargé ni connu de l'EntityManager de la requête en
 * cours. Cet objet porte un identifiant valide et des données à jour, mais
 * Doctrine ne le reconnaît pas dans son identity map.
 *
 * Conséquence si on le persiste ou l'associe tel quel à une autre entité
 * (ex. EmailChangeRequest::$requestedBy, EmailChangeValidation::$validator) :
 * Doctrine le traite comme une entité NEUVE et tente une RÉ-INSERTION avec
 * l'ID existant — constaté en developpememnt lors des test le 20/08 sous forme de
 * NotNullConstraintViolationException (colonne phone), symptôme d'un INSERT
 * là où une UPDATE était attendue.
 *
 * Utilisable dans n'importe quelle hiérarchie de contrôleur exposant
 * `getUser()` (Symfony\Bundle\FrameworkBundle\Controller\AbstractController
 * et, par héritage, Sonata\AdminBundle\Controller\CRUDController) —
 * volontairement un trait plutôt qu'une classe de base commune, puisque
 * TwoFactorAuthController et WlindablaAdminCRUDController n'ont pas
 * d'ancêtre commun autre que Symfony lui-même.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
trait ResolvesManagedCurrentUserTrait
{
    /**
     * @throws AccessDeniedException Si aucun utilisateur n'est authentifié,
     *                                 ou si son rechargement via find()
     *                                 échoue (compte supprimé entre-temps,
     *                                 incohérence de cache, etc.).
     */
    protected function resolveManagedCurrentUser(UserManagerInterface $userManager,UserInterface $currentUser): UserInterface
    {

        if (!$currentUser instanceof UserInterface || null === $currentUser->getId()) {
            throw new AccessDeniedException('Utilisateur courant introuvable ou invalide.');
        }

        $managedUser = $userManager->find($currentUser->getId());

        if (!$managedUser instanceof UserInterface) {
            throw new AccessDeniedException('Utilisateur courant introuvable ou invalide.');
        }

        return $managedUser;
    }
}
