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

namespace Wlindabla\UserBundle\UI\Http\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Wlindabla\UserBundle\Domain\User\Exception\EntityInUseException;

/**
 * EntityInUseExceptionListener — dédié à EntityInUseException (blocage
 * MÉTIER, jamais une question d'autorisation — cf. docblock de l'exception
 * elle-même). Volontairement séparé d'AccessDeniedListener : les deux
 * répondent à des questions différentes, pas de raison de les mélanger dans
 * un seul listener.
 *
 * ⚠️ Contrairement à un ExceptionListener générique "cache tout en prod",
 * le message de EntityInUseException est TOUJOURS affiché tel quel, y
 * compris en production — ce n'est jamais une fuite d'information technique,
 * juste une explication utile ("encore attribué à 3 personnes").
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsEventListener(event: ExceptionEvent::class, method: 'onEntityInUse', priority: 5)]
final class EntityInUseExceptionListener
{
    public function onEntityInUse(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof EntityInUseException) {
            return;
        }

        $request = $event->getRequest();

        if ($request->isXmlHttpRequest()) {
            $event->setResponse(new JsonResponse(
                [
                    'title' => 'Suppression impossible',
                    'message' => $exception->getMessage(),
                ],
                Response::HTTP_CONFLICT,
            ));
        }

       // Requête HTML classique (sans JS) : Sonata ne gère PAS ce cas
        // nativement (vérifié dans CRUDController::deleteAction() — seules
        // ModelManagerException/ModelManagerThrowable sont attrapées) — il
        // faut donc reproduire ici le même geste que Sonata fait pour ses
        // propres erreurs : flash + retour à la page précédente.
        if ($request->hasSession() ) {
             $currentSession = $request->getSession() ;
             if($currentSession instanceof Session) {
                $currentSession->getFlashBag()->add('sonata_flash_error', $exception->getMessage());
             }
        }

        $event->setResponse(new RedirectResponse($request->headers->get('referer') ?? '/'));
    }
}