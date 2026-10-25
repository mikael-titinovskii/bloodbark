<?php

namespace App\Mailer;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The "confirm your address" message on its own, for an account that asked for a fresh link from
 * the account page. Registration carries the same link inside `RegistrationMailer`.
 */
final readonly class ConfirmationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        #[Autowire('%app.mailer.sender%')] private string $senderAddress,
        #[Autowire('%app.mailer.sender_name%')] private string $senderName,
    ) {
    }

    public function send(User $user, string $token, string $locale): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->senderAddress, $this->senderName))
            ->to($user->getEmail())
            ->subject($this->translator->trans('confirmation.email.subject', locale: $locale))
            ->locale($locale)
            ->htmlTemplate('emails/email_confirmation.html.twig')
            ->textTemplate('emails/email_confirmation.txt.twig')
            ->context([
                'address' => $user->getEmail(),
                'locale' => $locale,
                'confirmationUrl' => $this->urls->generate('app_confirm_email', ['token' => $token, '_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        $this->mailer->send($email);
    }
}
