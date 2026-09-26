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

namespace Wlindabla\UserBundle\Domain\User\Service;

use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;

/**
 * Interface pour le flux "mot de passe oublié".
 *
 * Flux :
 * 1. L'utilisateur soumet son email/username -> requestReset() génère un token,
 *    le hash, le stocke, puis envoie (en asynchrone) un email de confirmation.
 * 2. L'utilisateur clique sur le lien reçu -> confirmReset() valide le token puis,
 *    par sécurité (utilisateurs du secteur santé), déclenche la génération d'un
 *    mot de passe temporaire envoyé par email (jamais de saisie de mot de passe
 *    en clair depuis un lien email non authentifié).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Infrastructure\Service
 */
interface PasswordResetInterface
{
    /**
     * Délai minimum entre deux demandes de réinitialisation (en secondes).
     * Protège contre le spam / l'énumération de comptes.
     */
    public const int RETRY_COOLDOWN = 60; // 1 minute

    /**
     * Durée de vie du lien de confirmation en secondes (1 heure).
     */
    public const int TOKEN_LIFETIME = 3600;

    /**
     * Traite une demande de réinitialisation de mot de passe.
     *
     * Ne lève jamais d'exception pour "utilisateur introuvable" : le comportement
     * observable côté client doit être identique que le compte existe ou non
     * (protection contre l'énumération de comptes).
     *
     * @param string $usernameOrEmail Identifiant ou email saisi par l'utilisateur
     */
    public function requestReset(string $usernameOrEmail): void;

    /**
     * Confirme le lien reçu par email et déclenche la génération du mot de passe
     * temporaire (envoyé par email à l'utilisateur).
     *
     * @param string     $rawToken Le token en clair reçu via l'URL
     * @param string|int $id       L'identifiant (déchiffré) de l'utilisateur
     *
     * @throws InvalidTokenException Si le token est invalide, expiré, déjà utilisé,
     *                                ou si l'utilisateur n'est pas trouvé
     */
    public function confirmReset(string $rawToken, string|int $id): void;
}
