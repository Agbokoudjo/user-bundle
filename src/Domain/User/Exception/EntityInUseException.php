<?php

declare(strict_types=1);

namespace Wlindabla\UserBundle\Domain\User\Exception;

/**
 * EntityInUseException — levée quand une suppression est bloquée pour une
 * raison MÉTIER (l'objet est encore référencé ailleurs), pas une raison
 * d'autorisation. Ne JAMAIS confondre avec AccessDeniedException — cf.
 * AccessDeniedListener, qui distingue les deux pour donner le bon code
 * HTTP et le bon message.
 */
final class EntityInUseException extends \RuntimeException
{
}