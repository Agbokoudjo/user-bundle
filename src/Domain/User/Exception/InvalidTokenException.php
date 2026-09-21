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

namespace Wlindabla\UserBundle\Domain\User\Exception;

use DomainException;

/**
 * Exception lancée lorsqu'un token de vérification utilisateur est invalide.
 * 
 * Cette exception couvre plusieurs cas d'invalidité :
 * - Token ne correspondant pas au hash stocké
 * - Token expiré
 * - Token déjà utilisé
 * - Utilisateur non trouvé
 * 
 * Elle est utilisée principalement pour :
 * - Confirmation d'email
 * - Réinitialisation de mot de passe
 * - Vérification 2FA
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Domain\User\Exception
 */
class InvalidTokenException extends DomainException
{
    /**
     * Code d'erreur : Token invalide (ne correspond pas au hash).
     */
    public const CODE_INVALID = 1001;

    /**
     * Code d'erreur : Token expiré (dépassement du délai de validité).
     */
    public const CODE_EXPIRED = 1002;

    /**
     * Code d'erreur : Token déjà utilisé (consommé précédemment).
     */
    public const CODE_ALREADY_USED = 1003;

    /**
     * Code d'erreur : Utilisateur introuvable pour ce token.
     */
    public const CODE_USER_NOT_FOUND = 1004;

    /**
     * Constructeur de l'exception.
     *
     * @param string $message Message d'erreur explicite
     * @param int $code Code d'erreur sémantique (utiliser les constantes CODE_*)
     * @param \Throwable|null $previous Exception précédente pour le chaînage
     */
    public function __construct(
        string $message = 'Le lien de vérification est invalide.',
        int $code = self::CODE_INVALID,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Crée une exception pour un token invalide (hash ne correspond pas).
     *
     * @param string|null $additionalInfo Informations supplémentaires à logger (jamais exposées à l'utilisateur)
     * 
     * @return self
     */
    public static function invalidToken(?string $additionalInfo = null): self
    {
        $message = 'Le lien de vérification est invalide ou a été modifié.';

        if ($additionalInfo !== null) {
            $message .= sprintf(' [Debug: %s]', $additionalInfo);
        }

        return new self($message, self::CODE_INVALID);
    }

    /**
     * Crée une exception pour un token expiré.
     *
     * @param \DateTimeInterface|null $expiredAt Date d'expiration du token
     * 
     * @return self
     */
    public static function expiredToken(?\DateTimeInterface $expiredAt = null): self
    {
        $message = 'Le lien de vérification a expiré. Veuillez en demander un nouveau.';

        if ($expiredAt !== null) {
            $message .= sprintf(' (Expiré le %s)', $expiredAt->format('d/m/Y à H:i'));
        }

        return new self($message, self::CODE_EXPIRED);
    }

    /**
     * Crée une exception pour un token déjà utilisé.
     *
     * @param \DateTimeInterface|null $usedAt Date d'utilisation du token
     * 
     * @return self
     */
    public static function alreadyUsedToken(?\DateTimeInterface $usedAt = null): self
    {
        $message = 'Ce lien de vérification a déjà été utilisé.';

        if ($usedAt !== null) {
            $message .= sprintf(' (Utilisé le %s)', $usedAt->format('d/m/Y à H:i'));
        }

        return new self($message, self::CODE_ALREADY_USED);
    }

    /**
     * Crée une exception lorsque l'utilisateur n'est pas trouvé.
     *
     * @return self
     */
    public static function userNotFound(): self
    {
        return new self(
            'Aucun compte utilisateur ne correspond à ce lien de vérification.',
            self::CODE_USER_NOT_FOUND
        );
    }

    /**
     * Vérifie si l'exception est due à un token expiré.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->getCode() === self::CODE_EXPIRED;
    }

    /**
     * Vérifie si l'exception est due à un token déjà utilisé.
     *
     * @return bool
     */
    public function isAlreadyUsed(): bool
    {
        return $this->getCode() === self::CODE_ALREADY_USED;
    }

    /**
     * Vérifie si l'exception est due à un utilisateur introuvable.
     *
     * @return bool
     */
    public function isUserNotFound(): bool
    {
        return $this->getCode() === self::CODE_USER_NOT_FOUND;
    }
}
