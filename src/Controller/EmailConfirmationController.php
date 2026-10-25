<?php

namespace App\Controller;

use App\Entity\User;
use App\Mailer\ConfirmationMailer;
use App\Security\RequestThrottle;
use App\Service\EmailConfirmationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Confirmation is a GET because it is what a link in a message does; the token is spent on the
 * first use, so opening it twice is harmless (the second visit finds nothing and says so). No
 * session is involved: the mailbox holder may well be on another device.
 */
final class EmailConfirmationController extends AbstractController
{
    public function __construct(private readonly RequestThrottle $throttle)
    {
    }

    #[Route('/confirm-email/{token}', name: 'app_confirm_email', methods: ['GET'], requirements: ['token' => '[0-9a-f]{64}'])]
    public function confirm(string $token, EmailConfirmationService $confirmations): Response
    {
        $user = $confirmations->confirm($token);

        if (null === $user) {
            return $this->render('email_confirmation/invalid.html.twig', [], new Response(status: Response::HTTP_GONE));
        }

        return $this->render('email_confirmation/confirmed.html.twig', ['email' => $user->getEmail()]);
    }

    /**
     * The account page's "send it again" button, for a link that expired in a mailbox or never
     * arrived. Signed in by definition, so the address is already the account's own.
     */
    #[Route('/account/confirmation', name: 'app_confirm_email_resend', methods: ['POST'])]
    public function resend(Request $request, EmailConfirmationService $confirmations, ConfirmationMailer $mailer): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('resend_confirmation', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->throttle->confirmation($request);

        if (!$user->isConfirmed()) {
            $mailer->send($user, $confirmations->resend($user), $request->getLocale());
            $this->addFlash('confirmation_sent', 'confirmation.resent');
        }

        return $this->redirectToRoute('app_account');
    }
}
