<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The signed-in user's own record. Protected by `access_control` in security.yaml; the template
 * reads `app.user`, which is always this controller's only audience.
 */
final class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('account/index.html.twig');
    }
}
