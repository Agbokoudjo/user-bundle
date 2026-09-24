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

namespace Wlindabla\UserBundle\UI\Http\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;
use Wlindabla\UserBundle\Domain\User\Service\EmailVerificationInterface;

/**instruction pour claude Ai: claude devrait nous dire comment on doit amlioerer cet controller
 * et comment on peut configurer pour que ça soit appeler lorsque la route est appeller 
 * less message des execeptions ,et les des templatest claude devrait nous dire comment le configurer pour que ça soit tradusable dans 
 * la langue de l'utilisateur par default,
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsController]
#[Route(
    '/verify-email/{token}/{id}',
    name: 'app.verify.email',
    methods: ['GET'],
    requirements: [
        'token' => '[a-zA-Z0-9]{32,64}'
    ],
)]

final class EmailVerificationController extends AbstractController
{
    public function __construct(
        private EmailVerificationInterface $verificationService,
        private readonly ?LoggerInterface $logger = null
    ) {}

    public function __invoke(
        string $token,
        int $id
    ):Response{
        $page_redirection_route_name= "app_admin_user_login";
        try {

            $this->verificationService->verifyEmail($token,$id);
            $this->addFlash('success', 'Votre email a été vérifié avec succès ! Vous pouvez maintenant vous connecter.');

            return $this->render('/email/security/checkEmail.html.twig', [
                'page_login_url' =>  'app_admin_user_login'
            ]);
            
        } catch (InvalidTokenException $e) {

            if ($e->isExpired()) {
                $this->addFlash(
                    'warning',
                    'Le lien de vérification a expiré. Un nouveau lien vous a été envoyé.'
                );

                // Renvoyer un email 
                $this->verificationService->resendVerificationEmail($id);

                return $this->render('/email/security/checkEmail.html.twig', [
                        'page_login_url' => $page_redirection_route_name
                    ]);
                }

            return $this->handleInvalidToken($e,$page_redirection_route_name);

        } catch (\Exception $e) {
            return $this->handleUnexpectedError($e);
        }
    }

    /**
     * Gère les différents cas d'erreur de token.
     *
     * @param InvalidTokenException $exception L'exception levée
     * @param string $slug Le slug de l'utilisateur
     * @param   string  $page_redirection_route_name
     * @return Response La route de redirection
     */
    private function handleInvalidToken(
        InvalidTokenException $e,
        string  $page_redirection_route_name
    ): Response{
      
         if ($e->isAlreadyUsed()) {
            $this->addFlash(
                'warning',
                'Votre email est déjà vérifié ! Vous pouvez vous connecter directement.'
            );
            
            return $this->render('/email/security/checkEmail.html.twig', [
                'page_login_url' => $page_redirection_route_name
            ]);

        } elseif ($e->isUserNotFound()) {

            $this->addFlash(
                'danger',
                '❌' . $e->getMessage()
            );
        } else {
            $this->addFlash(
                'danger',
                '❌ Le lien de vérification est invalide. Veuillez réessayer.'
            );
        }

        return $this->render('/email/security/checkEmail.html.twig', [
            'page_redirection_url' => 'app_home',
            'page_label' => 'Aller a la page d\'accueill'
        ]);
    }  

    /**
     * Gère les erreurs inattendues.
     *
     * @param \Exception $exception L'exception levée
     * 
     * @return Response
     */
    private function handleUnexpectedError(\Exception $exception): Response
    {
        $this->logger?->critical('Erreur inattendue lors de la vérification d\'email', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->addFlash(
            'error',
            '⚠️ Une erreur est survenue. Veuillez réessayer plus tard ou contacter le support.'
        );
       
        return $this->render('/email/security/checkEmail.html.twig', [
            'page_redirection_url' => 'app_home',
            'page_label' => 'Aller a la page d\'accueill'
        ]);
    }
}
