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

namespace Wlindabla\UserBundle\Domain\UserSession\Application\Manager;

use App\WlindablaUserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;
use App\WlindablaUserBundle\Domain\UserSession\Model\UserSessionInterface;

/**
 * Contrat du service de gestion des sessions utilisateur uniques.
 *
 * Toutes les méthodes qui mutent l'état (create, update, remove) sont
 * dispatchables de manière asynchrone via AsyncMethodDispatcherInterface.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
interface UserSessionManagerInterface
{
    /**
     * Crée une session pour l'utilisateur identifié et révoque (raison CONFLICT)
     * toutes ses sessions précédentes (politique single-session).
     */
    public function createSession(
        string $userIdentifier,
        string $sessionId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceFingerprint = null,
    ): UserSessionInterface;

    /**
     * Retourne la session active la plus récente d'un utilisateur,
     * ou null s'il n'en a pas.
     */
    public function findActiveSession(string $userIdentifier): ?UserSessionInterface;

    /**
     * Retourne une session par son PHPSESSID, active OU révoquée — permet
     * à SessionControlListener de distinguer "jamais créée" de "révoquée".
     */
    public function findBySessionId(string $sessionId): ?UserSessionInterface;

    /**
     * Indique si l'utilisateur possède une session active dont l'ID
     * est DIFFÉRENT de $currentSessionId (= conflit de session).
     */
    public function hasActiveSession(string $userIdentifier, string $currentSessionId): bool;

    /**
     * Retourne les métadonnées de la session active de l'utilisateur
     * sous forme de tableau normalisé, ou null si aucune session.
     */
    public function getSessionInfo(string $userIdentifier): ?array;

    /**
     * Révoque (soft) :
     *  - les sessions actives inactives depuis plus de $hoursOfInactivity heures
     *    (raison EXPIRED_INACTIVITY) ;
     *  - les sessions actives créées il y a plus de $maxDurationHours heures,
     *    même si l'utilisateur est resté actif en continu (raison
     *    EXPIRED_MAX_DURATION) — aligné sur la durée du cookie remember_me.
     *
     * Appelé périodiquement par le Scheduler.
     *
     * @return array{inactivity: UserSessionInterface[], maxDuration: UserSessionInterface[]}
     */
    public function cleanExpiredSessions(int $hoursOfInactivity, int $maxDurationHours): array;

    /**
     * Révoque toutes les sessions actives d'un utilisateur avec la raison donnée.
     * Utilisé aussi lors de la déconnexion explicite.
     */
    public function revokeAllActiveSessions(
        string $userIdentifier,
        UserSessionRevokedReason $reason = UserSessionRevokedReason::CONFLICT,
    ): void;

    /**
     * Révoque une session spécifique par son ID de session PHP.
     * Appelé lors de la déconnexion (LogoutSubscriber).
     */
    public function removeSession(
        string $sessionId,
        UserSessionRevokedReason $reason = UserSessionRevokedReason::MANUAL_LOGOUT,
    ): void;

    /**
     * Met à jour le timestamp de dernière activité de la session.
     *
     * Utilise un verrou cache pour limiter les écritures SQL à une fois
     * par heure maximum (évite un flush à chaque requête HTTP).
     */
    public function updateSessionActivity(string $sessionId): void;
}
