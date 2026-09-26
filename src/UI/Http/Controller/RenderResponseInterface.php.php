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

use Symfony\Component\HttpFoundation\Response;
/**
 * this interface have created because this message "Cannot access protected method Symfony\Bundle\FrameworkBundle\Controller\AbstractController::render() from OutcomePageRenderer scope.intelep"
 * the controller call Wlindabla\UserBundle\Infrastructure\Templating\OutcomePageRenderer
 *  HTTP have implemneted this interface for pass 
 *Instruction claude AI va nous suggerer un bon nom  pour l'interface 
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface RenderResponseInterface
{
    public function renderResponse(string $view, array $parameters = [], ?Response $response = null): Response ;
}