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

use App\Infrastructure\Exception\InvalidTokenException;

/**
 * Interface pour la vérification des emails utilisateurs.
 * 
 * Définit le contrat pour valider les tokens de confirmation d'email
 * et activer les comptes utilisateurs après vérification.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Domain\User\Service
 */
interface EmailVerificationInterface
{
    /**
     * Délai minimum entre deux demandes de vérification (en secondes).
     */
    public const RESEND_COOLDOWN = 60; // 1 minute

    /**
     * Durée de vie d'un token de vérification en secondes (1 heure).
     */
    public const TOKEN_LIFETIME = 3600;

    /**
     * Valide le token de confirmation et active le compte utilisateur.
     * 
     * Cette méthode effectue les vérifications suivantes :
     * - Existence de l'utilisateur avec le slug fourni
     * - Validité du token (comparaison avec le hash stocké)
     * - Non-expiration du token (selon TOKEN_LIFETIME)
     * - Token non déjà utilisé
     * 
     * Si toutes les vérifications passent :
     * - Marque l'email comme vérifié
     * - Supprime le token de confirmation
     * - Active automatiquement le compte pour les utilisateurs simples (Client, Simple)
     * - Les comptes Admin/Member nécessitent une activation manuelle par un administrateur
     *
     * @param string $rawToken Le token en clair reçu via l'URL de vérification
     * @param string|int $id l'identifiant unique de l'utilisateur
     * 
     * @return void
     * 
     * @throws InvalidTokenException Si le token est invalide, expiré, déjà utilisé, ou si l'utilisateur n'est pas trouvé
     *                               - Code CODE_INVALID (1001) : Token ne correspond pas au hash
     *                               - Code CODE_EXPIRED (1002) : Token a dépassé sa durée de vie
     *                               - Code CODE_ALREADY_USED (1003) : Token déjà consommé
     *                               - Code CODE_USER_NOT_FOUND (1004) : Utilisateur introuvable
     */
    public function verifyEmail(string $rawToken, string|int $id): void;


    /**
     * Génère et envoie un nouveau token de vérification d'email.
     * La génération et le rate limiting sont délégués à SecureTokenService.
     * Cette méthode :
     * 1. Vérifie l'existence de l'utilisateur
     * 2. Vérifie si l'email n'est pas déjà vérifié
     * 3. Applique un rate limiting (délai minimum entre demandes)
     * 4. Génère un nouveau token cryptographiquement sécurisé
     * 5. Hashe et stocke le token
     * 6. Déclenche l'événement d'envoi d'email
     *
     * @param string|int $id l'identifiant unique de l'utilisateur
     * 
     * @return void
     * 
     * @throws InvalidTokenException Si l'utilisateur n'existe pas, l'email est déjà vérifié,
     *                               ou si le délai minimum entre demandes n'est pas respecté
     * @throws \RuntimeException Si la génération ou le hachage du token échoue
     */
    public function resendVerificationEmail(string|int $id): void;
}
