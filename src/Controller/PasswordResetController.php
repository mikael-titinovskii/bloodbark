<?php

namespace App\Controller;

use App\Form\PasswordResetRequestType;
use App\Form\ResetPasswordType;
use App\Security\RequestThrottle;
use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Two public pages and one link. Asking for a reset always ends on the same confirmation, whether
 * or not the address is registered, so the form cannot be used to enumerate accounts.
 *
 * Opening a link does nothing by itself: the token is only spent by the POST that sets the new
 * password, because mail clients and scanners open links on their own.
 */
final class PasswordResetController extends AbstractController
{
    public function __construct(private readonly RequestThrottle $throttle)
    {
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function request(Request $request, PasswordResetService $resets): Response
    {
        // Deliberately open to signed-in visitors too: an account holder who cannot log in on
        // another device, or who simply wants a new password, has to be able to ask for a link.
        $form = $this->createForm(PasswordResetRequestType::class);
        $form->handleRequest($request);

        // Throttled on submission rather than on a valid address: an unknown address sends no mail
        // but still costs a lookup, and the answer must stay identical.
        if ($form->isSubmitted()) {
            $this->throttle->passwordReset($request);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            \assert(\is_string($email));

            $resets->request($email, $request->getLocale());

            return $this->redirectToRoute('app_forgot_password_sent');
        }

        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('password_reset/request.html.twig', ['form' => $form], new Response(status: $status));
    }

    #[Route('/forgot-password/sent', name: 'app_forgot_password_sent', methods: ['GET'])]
    public function sent(): Response
    {
        return $this->render('password_reset/sent.html.twig', ['hours' => PasswordResetService::TTL_HOURS]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'], requirements: ['token' => '[0-9a-f]{64}'])]
    public function reset(string $token, Request $request, PasswordResetService $resets): Response
    {
        $resetRequest = $resets->findUsable($token);

        if (null === $resetRequest) {
            return $this->render('password_reset/invalid.html.twig', [], new Response(status: Response::HTTP_GONE));
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = $form->get('plainPassword')->get('first')->getData();
            \assert(\is_string($password));

            $resets->reset($resetRequest, $password);
            $this->addFlash('reset_done', 'reset.done.message');

            return $this->redirectToRoute('app_login');
        }

        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('password_reset/reset.html.twig', [
            'form' => $form,
            'email' => $resetRequest->getUser()->getEmail(),
        ], new Response(status: $status));
    }
}
