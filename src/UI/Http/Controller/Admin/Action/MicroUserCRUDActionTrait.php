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

use App\WlindablaUserBundle\Domain\User\Application\CommandHandler\ToggleUserAccountHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\UserInterface as SymfonySecurityUserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\GenerateTemporaryPasswordHandler;
use Wlindabla\UserBundle\Domain\User\Application\Dto\SubmittedUserDto;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Enum\AccountStatus;
use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;
use Wlindabla\UserBundle\Domain\User\Service\EmailVerificationInterface;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;
use Wlindabla\UserBundle\Infrastructure\Service\UserDuplicateFinderService;

/** instruction pour claude AI: ecrire la doc PHPDOcs de cet trait correctement
 * cet micro controller doit inclure dans les controller CRUD de votre application ou projet 
 * si vous utiliser sonataAdmin faite simplement MicroUserCRUDActionTrait danns le controller crud csutomiser 
 * tout en heritant le controller de base de sonataAdmin(CRUDController) comme class WlindablaAdminCRUDController extends CRUDController {
 * sonataAdmin gerer le routage nativement bien sure que si vous configurer la route dans la methode protected function configureRoute
 * protected function configureRoutes(RouteCollectionInterface $collectionRoutes): void
    {
        parent::configureRoutes($collectionRoutes);

        $collectionRoutes->add(
            name: 'toggle_enabled_user_account',
            pattern: 'toggle_enabled_user_account/{id}',
            defaults: [
                '_controller' => AdminUserCRUDController::class . '::toggleEnabledUserAccountAction',
                '_sonata_admin' => $this->getCode(),
            ],
            methods: ['PATCH'],
        );

        $collectionRoutes->add(
            name: 'resend_email_verify',
            pattern: 'resend_email_verify/{id}',
            defaults: [
                '_controller' => AdminUserCRUDController::class . '::resendEmailVerifyAction',
                '_sonata_admin' => $this->getCode(),
            ],
            methods: ['GET'],
        );
 
        $collectionRoutes->add(
            name: 'regenerate_tempory_password_user',
            pattern: 'regenerate_tempory_password_user/{id}',
            defaults: [
                '_controller' => AdminUserCRUDController::class . '::regenerateTemporyPasswordUserAction',
                '_sonata_admin' => $this->getCode(),
            ],
            methods: ['PATCH'],
        );

        $collectionRoutes->add(
            name: 'request_email_change',
            pattern: 'request_email_change/{id}',
            defaults: [
                '_controller' => AdminUserCRUDController::class . '::requestEmailChangeAction',
                '_sonata_admin' => $this->getCode(),
            ],
            methods: ['POST'],
        );

        $collectionRoutes->add(
            name: 'request_email_recovery',
            pattern: 'request_email_recovery/{id}',
            defaults: [
                '_controller' => AdminUserCRUDController::class . '::requestEmailRecoveryAction',
                '_sonata_admin' => $this->getCode(),
            ],
            methods: ['POST'],
        );
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
           
            ->add(ListMapper::NAME_ACTIONS, ListMapper::TYPE_ACTIONS, [
                'translation_domain' => 'SonataAdminBundle',
                'actions' => [
                    'edit' => [],
                    'show' => [],
                    'delete' => [],
                    'toggle_enabled_user_account' => [
                        'template' => 'bundles/SonataAdminBundle/CRUD/list_action_toggle_enabled_user_account.html.twig',
                    ],
                    'resend_email_verify' => [
                        'template' => 'bundles/SonataAdminBundle/CRUD/list_action_resend_email_verify.html.twig',
                    ],
                    'regenerate_tempory_password_user' => [
                        'template' => 'bundles/SonataAdminBundle/CRUD/list_action_regenerate_tempory_password_user.html.twig'
                    ],
                    'request_email_change' => [
                        'template' => 'bundles/SonataAdminBundle/CRUD/list_action_request_email_change.html.twig'
                    ],
                    'request_email_recovery' => [
                        'template' => 'bundles/SonataAdminBundle/CRUD/list_action_request_email_recovery.html.twig'
                    ]
                ],
                'label' => 'Actions'
            ]);
    } 
    la gestion des droits depends de vous c'est facile a le faire avec sonataAdmin et symfony7+ 
 * mais si vouler n'utiliser pas sonataAdmin inclurer quand meme mais le routage depends du bundle ou outilise admin que vous utiliser 
 * par exemple si vous utiliser EasyAdmin ou d'autre famework comme Laravel qui utilise des composant de symfony
 * vous devez gerer le routage ou la configuration de l'outil ou famework 
 * 
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 *
 */
trait MicroUserCRUDActionTrait
{
    public function resendEmailVerifyAction(
        Request $request,
        string |int $id,
        EmailVerificationInterface $verificationService,
        UserManagerInterface $userManager
    ):JsonResponse{

         if (!$this->isXmlHttpRequest($request) || !$request->isMethod('GET')) {
            throw new BadRequestHttpException('Requête non autorisée. Veuillez utiliser le canal GET/AJAX approprié.');
        }

        try{
            // Validation du slug
            if (!$id || empty(trim((string) $id))) {
                throw new BadRequestHttpException('LL\'identifiant de l\'utilisateur est manquant.');
            }

             /** @var UserInterface|null */
            $user = $userManager->find($id);

            if (!($user instanceof UserInterface)) {
                return $this->createFlashJsonResponse(
                    type: "warning",
                    title: 'Attention',
                    message: \sprintf('❌ Aucun utilisateur trouvé avec l\'identifiant "%s".', $id),
                    statusCode: Response::HTTP_NOT_FOUND
                );
            }

            if ($user->isEmailVerified()) {
                return $this->createFlashJsonResponse(
                        type: "warning",
                        title: 'Action Bloquée : Email déja Vérifié',
                        message:  \sprintf(
                            'L\'adresse Email "%s" associée au compte "%s" a pas été vérifiée. Cette action est donc bloquée.',
                            $user->getEmail(),
                            $user->getUsername()
                        )
                    ,
                    statusCode:Response::HTTP_FORBIDDEN
                );
            }

            $verificationService->resendVerificationEmail($id);
            $type = "success";
            $title = "Email de Vérification Envoyé !";
            $message = \sprintf(
                "Le lien de vérification a été renvoyé avec succès. Veuillez informer l'utilisateur de consulter sa boîte de réception ou ses spams pour confirmer son compte.",
            );
            $statusHttp = Response::HTTP_OK;
        }catch(BadRequestHttpException |InvalidTokenException |\RuntimeException|\Exception $e){
            $type = "danger";
            $title = "Échec de l'Envoi"; 
            $message = $e->getMessage();
            $statusHttp = Response::HTTP_BAD_REQUEST;
        }

        return $this->createFlashJsonResponse(
            type: $type,
            title: $title,
            message: $message,
            statusCode: $statusHttp
        );
    }
    
    /**
     * Active ou désactive un compte utilisateur.
     * 
     * Le UserType est automatiquement résolu et décodé par UserTypeArgumentValueResolver.
     * Pas besoin de le décoder manuellement.
     *
     * @throws BadRequestHttpException Si les données ne sont pas valides
     */
    public function toggleEnabledUserAccountAction(
        Request $request,
        string|int $id,
        #[CurrentUser] SymfonySecurityUserInterface $currentUserConnected,
        UserManagerInterface $userManager,
        AsyncMethodDispatcherInterface $asyncMethodDispatcher
    ): JsonResponse {
        
        if (!$this->isXmlHttpRequest($request) || !$request->isMethod('PATCH')) {
            throw new BadRequestHttpException('Requête non autorisée. Veuillez utiliser le canal PATCH/AJAX approprié.');
        }

        try {
            // Validation du slug
            if (!$id || empty(trim((string) $id))) {
                throw new BadRequestHttpException('L\'identifiant de l\'utilisateur est manquant.');
            }

            /** @var UserInterface|null */
            $user = $userManager->find($id);

            if (!($user instanceof UserInterface)) {
                return $this->createFlashJsonResponse(
                    type:"warning",
                    title: 'Warning',
                    message: \sprintf('❌ Aucun utilisateur trouvé avec l\'identifiant "%s".', $id),
                    statusCode: Response::HTTP_NOT_FOUND
                );
            }

            if (!($user->isEmailVerified())) {
                return $this->createFlashJsonResponse(
                        type:"danger",
                        title :'Action Bloquée : Email Non Vérifié',
                        message : \sprintf(
                            'L\'adresse Email "%s" associée au compte "%s" n\'a pas été vérifiée. Cette action est donc bloquée. Veuillez cliquer sur le bouton "Renvoyer l\'email de vérification" pour procéder.',
                            $user->getEmail(),
                            $user->getUsername() 
                        ),
                    statusCode:Response::HTTP_FORBIDDEN
                );
            }
            
            // Récupérer et valider le nouveau statut
            $isEnabled = $this->getStatusFromRequest($request);
            $accountStatus = $isEnabled ? AccountStatus::ACTIVE : AccountStatus::INACTIVE;

            if(($currentUserConnected instanceof UserInterface) && 
                $currentUserConnected->getId() === $user->getId()){
                if($isEnabled ===false){
                    return $this->createFlashJsonResponse(
                        type: "danger",
                        title: 'Erreur',
                        message: "Impossible de desactiver son compte utilisateur en étant connecté.",
                        statusCode: Response::HTTP_BAD_REQUEST
                    );
                }
            }

            $user->setEnabled($isEnabled);

            $this->dispatchToggleUserAccountTask($user,$accountStatus, $asyncMethodDispatcher);

            $message = $this->buildSuccessMessage($user, $isEnabled);

            return $this->createFlashJsonResponse(
                 type:"success",
                title: 'Opération réussie',
                message: $message,
                statusCode: Response::HTTP_OK
            );
        } catch (BadRequestHttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return $this->createFlashJsonResponse(
                type:"danger",
                title: 'Error',
                message: 'Une erreur est survenue lors de la mise à jour du statut.Veuillez actualiser et réessayer.',
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function regenerateTemporyPasswordUserAction(
        Request $request,
        int|string $id,
        #[CurrentUser] SymfonySecurityUserInterface $currentUserConnected,
        UserManagerInterface $userManager,
        AsyncMethodDispatcherInterface $asyncMethodDispatcher
       ):JsonResponse{

        if (!$this->isXmlHttpRequest($request) || !$request->isMethod('PATCH')) {
            throw new BadRequestHttpException('Requête non autorisée. Veuillez utiliser le canal PATCH/AJAX approprié.');
        }

         // Validation du id
        if (!$id || empty(trim((string) $id))) {
            return $this->createFlashJsonResponse(
                type: "error",
                title: 'Donnée Manquante',
                message: 'L\'identifiant utilisateur (ID) est requis et ne peut être vide.',
                statusCode: Response::HTTP_BAD_REQUEST
            );
        }

        /** @var UserInterface|null */
        $user = $userManager->find($id);

        if (!($user instanceof UserInterface)) {
            return $this->createFlashJsonResponse(
                type: "warning",
                title: 'Attention',
                message: \sprintf('❌ Aucun utilisateur trouvé avec l\'identifiant "%s".', $id),
                statusCode: Response::HTTP_NOT_FOUND
            );
        }
        
        if (!($user->isEmailVerified())) {
                return $this->createFlashJsonResponse(
                        type: "warning",
                        title :'Action Bloquée : Email Non Vérifié',
                        message : \sprintf(
                            'L\'adresse Email "%s" associée au compte "%s" n\'a pas été vérifiée. Cette action est donc bloquée. Veuillez cliquer sur le bouton "Renvoyer l\'email de vérification" pour procéder.',
                            $user->getEmail(),
                            $user->getUsername() 
                        )
                        ,
                    statusCode:Response::HTTP_FORBIDDEN
                );
            }

        if(($currentUserConnected instanceof UserInterface) && $currentUserConnected->getId() === $user->getId()){
            return $this->createFlashJsonResponse(
                type: "danger",
                title: 'Erreur',
                message: 'Impossible de regenerer ou de changer son mot de pass  pour son  compte utilisateur en étant connecté.Veuillez cliquer sur le bouton "Mots de Passe oublié sur la page de connexion ou d\'authentification" pour procéder.',
                statusCode: Response::HTTP_BAD_REQUEST
            );
            }

        try {
           $asyncMethodDispatcher->dispatch(
                GenerateTemporaryPasswordHandler::class,
                'process',
                [$id]
            );

            $type = "success";
            $title = "Mot de Passe Temporaire Généré et Envoyé !";
            $message = "Un nouveau mot de passe temporaire a été généré avec succès. Une notification a été immédiatement envoyée à la boîte e-mail de l'utilisateur pour qu'il puisse se connecter et le modifier.";
            $statusHttp = Response::HTTP_OK;
        } catch (\Exception $e) {
            $type = "danger";
            $title = "Échec de la Réinitialisation";
            $message = "Une erreur est survenue lors du processus de régeneration du mot de passe : " . $e->getMessage();
            $statusHttp = Response::HTTP_BAD_REQUEST;
        }

        return $this->createFlashJsonResponse(
            type: $type,
            title: $title,
            message: $message,
            statusCode: $statusHttp
        );
    }
    
     /**
     * Crée une réponse JSON avec flash message si possible
     * vu que WlindablaUser est un bundle ,le frontend qui consome cet outils doit changer 
     * de faire comme un message flash par exemple le fontend est (html et css ,javascript) 
     * il est conseiller d'utiliser les composants Alert de message flash de Boostrap5.x+ ou d'autre bibliotheque css de 
     * votre choix,ou exporter @wlindabla/form_validator pour explorer les differents options(ex:dialogue de la bibliotheque) pour ceux qui utilise html ,css et javascript
     * si c'est une application mobiule qui sommme le bundle et qui s'est cet bundle comme backend symfony ,il doit as'arranger de faire 
     * un message flash
     * il est consultrer d'utilis
     */
    final protected function createFlashJsonResponse(
        string  $type,
        string $title,
        string $message,
        int $statusCode = Response::HTTP_OK
    ): JsonResponse {
        return new JsonResponse(
            [
                'title' => $title,
                'type' => $type,
                'message' => $message,
            ],
            $statusCode
        );
    }

      /**
     * Récupère et valide le statut de la requête
     * 
     * @throws BadRequestHttpException Si le statut est invalide
     */
    protected function getStatusFromRequest(Request $request): bool{

        $status=$request->getPayload()->getBoolean('status');

        if ($status === null) {
            throw new BadRequestHttpException('Le paramètre "status" est manquant.');
        }

        if (\is_bool($status)) { return $status;}

        if (\is_string($status)) {
            $status = strtolower($status);

            if (\in_array($status, ['true', '1', 'on', 'yes'], true)) {
                return true;
            }

            if (\in_array($status, ['false', '0', 'off', 'no'], true)) {
                return false;
            }

        }

        throw new BadRequestHttpException(
            sprintf('Statut invalide : "%s". Utiliser "true" ou "false".', $status)
        );
    }


     /**
     * Returns true if the request is a XMLHttpRequest.
     *
     * @return bool True if the request is an XMLHttpRequest, false otherwise
     */
    final protected function isXmlHttpRequest(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || $request->request->getBoolean('_xml_http_request')
            || $request->query->getBoolean('_xml_http_request');
    }

      /**
     * Construit le message de succès percutant
     */
    final protected function buildSuccessMessage(UserInterface $user, bool $isEnabled): string
    {
        $action = $isEnabled
            ? 'Compte activé avec succès'
            : 'Compte désactivé avec succès';

        return \sprintf(
            '%s - %s (ID: %s)',
            $action,
            $user->getUsername(),
            $user->getId()
        );
    }

    /**
     * Dispatch la tâche async de basculement de compte
     */
    final protected function dispatchToggleUserAccountTask(
        UserInterface $user,
        AccountStatus $accountStatus,
        AsyncMethodDispatcherInterface $asyncMethodDispatcher
    ): void {
        $asyncMethodDispatcher->dispatch(
            ToggleUserAccountHandler::class,
            'handle',
            [$user->getId(), $accountStatus]
        );
    } 


    /**
     * utilisation juste apres la validation des données avec symfony Validator du formulaire
     * si vous utiliser sonataAdmin appeller ou sucharger protected function preCreate(Request $request,object $newObject): ?Response
     * 
     * a utiliser comme 
    * @example 
    final class AdminUserCRUDController extends WlindablaAdminCRUDController  ou CRUDController of Sonata ou CustomerCRUDController{
       use  validateUser
    public function __construct(
        private readonly UserManager $userManager,
        private readonly SonataUserDuplicateFinder $userDuplicateFinder,
        private readonly AsyncMethodDispatcherInterface $asyncMethodDispatcher) {}

            protected function preCreate(Request $request,object $newObject): ?Response
            {
                if(!$request->isMethod("POST")) {return null ;}

                $submittedUsername = $request->request->get('username');
                $submittedEmail=$request->request->get('email');

                try {
                    return $this->validateUser(
                    new SubmittedUserDto($submittedUsername,$submittedEmail),
                    $this->userManager,
                    $this->userDuplicateFinder,
                    $this->asyncMethodDispatcher
                );
                } catch (\Throwable $th) {
                    throw $th;
                }
            }
        }
     */
    protected function validateUser(
        SubmittedUserDto $submittedUserDto, 
        UserManagerInterface $userManager,
        UserDuplicateFinderService $userDuplicateFinder,
        AsyncMethodDispatcherInterface $asyncMethodDispatcher,
    ):?Response{

    /**
     * @var UserInterface|null $existingUser
     */
    $existingUser = $userManager->findOneByUsernameOrEmail($submittedUserDto->submittedUsername,$submittedUserDto->submittedUserEmail);

        if ($existingUser !== null) {
            $asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                "warning",
                [
                    "message"=> 'Cet utilisateur existe déjà dans la base de données. La création de compte a été bloquée.',
                    "context"=> [
                        'email' => $submittedUserDto->submittedUserEmail,
                        'existingAccountRequestId' =>   $existingUser->getId(),
                    ]
                ]
            );

            return $this->createFlashJsonResponse(
                type: "warning",
                title: 'Création de compte non traitée',
                message :"Cet utilisateur existe déjà dans la base de données. La création de compte a été bloquée.",
                statusCode: Response::HTTP_CONFLICT);
        }

        $duplicates = $userDuplicateFinder->findDuplicates($submittedUserDto->submittedUsername ?? '');

        if ([] !== $duplicates) {
            $asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                "warning",
                [
                    "message" => 'Création de compte bloquée : nom similaire à un compte utilisateur existant',
                    "context" =>  [
                        'submittedUsername' => $submittedUserDto->submittedUsername,
                        'submittedEmail' => $submittedUserDto->submittedUserEmail,
                        'matchedAccountRequestIds' => array_column($duplicates, 'id'),
                        'trigramScores' => array_column($duplicates, 'trigramScore'),
                    ]
                ]
            );
            
            return $this->createFlashJsonResponse(
                type: "warning",
                title: 'Création de compte non traitée',
                message :"Création de compte bloquée : le nom de cet utilisateur est similaire à un compte utilisateur existant.",
                statusCode: Response::HTTP_CONFLICT);
        }

         return null;
    }
}
