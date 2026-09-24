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

namespace Wlindabla\UserBundle\Infrastructure\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Wlindabla\ShareServiceBundle\Messenger\Message\ServiceMethodMessage;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\UpdateUserProfileHandler;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Exception\EmailAlreadyVerifiedException;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Infrastructure\Service\SecureTokenService;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final readonly class UpdateProfileSuccessListener implements EventSubscriberInterface
{
    public function __construct(
        private UserManagerInterface $userManager,
        private SecureTokenService $tokenGenerateService,
        private LoggerInterface $logger
    ){}

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class=> 'onWorkerMessageHandled'
        ];
    }

    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event): void{

        // Vérifier si le message traité est celui qui nous intéresse
        $message = $event->getEnvelope()->getMessage();

        if(!($message instanceof ServiceMethodMessage)){ return ;}

        // Vérifier si le message exécuté était bien notre CommandHandler de profil
        if ($message->getServiceName() !== UpdateUserProfileHandler::class 
            || $message->getMethod() !== 'handle') { 
            return;
        }

        // Extraire l'objet UpdateUserProfileCommand réel
        $params = $message->getParams();
        if (empty($params) || (!\is_string($params[0]) && !\is_numeric($params[0]))) {

            //  Loguer que l'argument attendu n'est pas présent 
            $this->logger->error(
                'Échec de l\'exécution asynchrone : Le CommandHandler de profil a été appelé sans l\'objet de commande attendu.',
                [
                    'expected_command' => 'id' ,
                    'service' => $message->getServiceName(),
                    'method' => $message->getMethod(),
                    'params_count' => \count($params),
                    'params_type' => empty($params) ? 'aucun' : get_debug_type($params[0]),
                ]
            );
            return;
        }

        /** @var string|int $command */
        $command = $params[0];

        try {
            /**
             * @var BaseUserInterface
             */
            $user = $this->userManager->find($command);
        } catch (\Exception $e) {
            $this->logAndThrowCriticalException($e, $command);
            throw $e;
        }

        // 5. Vérifier si l'utilisateur est présent et non vérifié
        if (null === $user || $user->isEmailVerified()) {
            $this->logIgnoredSend($user, $command);
            return;
        }
       
        try {
            // Le service s'occupe de la génération, du hachage, de la persistance, et du dispatch de l'événement d'email.
            $this->tokenGenerateService->generateEmailConfirmationToken($user);
        } catch (EmailAlreadyVerifiedException $e) {
            // Cas théorique : L'utilisateur est vérifié entre les points 5 et 6.
            // On log et on arrête. Pas de relance.
            $this->logger->warning(
                'Tentative de génération de token pour un email déjà vérifié après le contrôle asynchrone.',
                ['exception' => $e, 'user_id' => $command,"message"=>$e->getMessage()]
            );

            return;

        } catch (\RuntimeException $e) {
            // Erreur liée à l'utilisateur (Ex: Cool-down / Rate Limiting).
            // L'utilisateur ne peut rien faire; le message ne doit pas être rejoué car le temps d'attente
            // du cool-down sera dépassé lors du prochain rejeu. On log et on arrête.
            $this->logger->notice(
                'Échec d\'envoi d\'email en raison du Cool-down/Rate Limiting.',
                ['exception' => $e, 'user_id' => $command, 'type' => 'Cool-down',"message"=>$e->getMessage()]
            );

            return;

        } catch (\InvalidArgumentException $e) {
            // Erreur de logique de développement (Ex: Email vide, longueur invalide). 
            // Doit être traitée comme une erreur critique de configuration/code.
            $this->logAndThrowCriticalException($e, $command);
        } catch (\Exception $e) {
            // Toute autre erreur non prévue (Ex: Problème de BDD lors du save, erreur de hachage).
            // Le Worker doit échouer et retenter.
            $this->logAndThrowCriticalException($e, $command, 'Erreur inattendue lors de la génération du token.');
        }
    }

    /** Gère le logging critique et la relance pour les erreurs de développement. */
    private function logAndThrowCriticalException(\Throwable $e, int|string $command, string $message = 'Erreur critique de développement.'): never
    {
        $this->logger->critical($message, [
            'exception' => $e,
            'user_id' => $command,
            'code' => 'CRIT_002',
            "message"=>$e->getMessage()
        ]);
        throw $e;
    }

    /** Gère le logging pour les envois d'email ignorés. */
    private function logIgnoredSend(?BaseUserInterface $user, string|int $command): void
    {
        $reason = null === $user ? 'utilisateur non trouvé' : 'utilisateur déjà vérifié';

        $this->logger->notice(
            'Envoi de l\'e-mail de vérification ignoré : le traitement asynchrone n\'est plus nécessaire.',
            [
                'reason' => $reason,
                'user_id' => $command
            ]
        );
    }
}