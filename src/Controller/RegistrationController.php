<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Security\RequestThrottle;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    public function __construct(private readonly RequestThrottle $throttle)
    {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, RegistrationService $registration): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_account');
        }

        $form = $this->createForm(RegistrationType::class, new User());
        $form->handleRequest($request);

        // Every submission counts, valid or not: what this guards is the mail, and a rejected form
        // still walks the same path up to validation.
        if ($form->isSubmitted()) {
            $this->throttle->registration($request);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();

            $registration->register($user, $request->getLocale());
            $this->addFlash('registration_sent', $user->getEmail());

            return $this->redirectToRoute('app_register_sent');
        }

        // A rejected submission is reported as 422 rather than 200, so the browser does not treat
        // it as a fresh page and scraper-like clients see that nothing was accepted.
        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('registration/register.html.twig', ['form' => $form], new Response(status: $status));
    }

    #[Route('/register/sent', name: 'app_register_sent', methods: ['GET'])]
    public function sent(Request $request): Response
    {
        $session = $request->getSession();

        if (!$session instanceof FlashBagAwareSessionInterface) {
            return $this->redirectToRoute('app_register');
        }

        $email = $session->getFlashBag()->get('registration_sent')[0] ?? null;

        if (!\is_string($email)) {
            return $this->redirectToRoute('app_register');
        }

        return $this->render('registration/sent.html.twig', ['email' => $email]);
    }
}
