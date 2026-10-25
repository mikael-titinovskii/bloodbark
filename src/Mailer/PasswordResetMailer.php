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
 * Mails the one-time link. The token itself never touches the database, so this message is the
 * only place the usable value exists.
 */
final readonly class PasswordResetMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        #[Autowire('%app.mailer.sender%')] private string $senderAddress,
        #[Autowire('%app.mailer.sender_name%')] private string $senderName,
    ) {
    }

    public function sendLink(User $user, string $token, string $locale, int $validForHours): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->senderAddress, $this->senderName))
            ->to($user->getEmail())
            ->subject($this->translator->trans('reset.email.subject', locale: $locale))
            ->locale($locale)
            ->htmlTemplate('emails/password_reset.html.twig')
            ->textTemplate('emails/password_reset.txt.twig')
            ->context([
                'address' => $user->getEmail(),
                'locale' => $locale,
                'hours' => $validForHours,
                'resetUrl' => $this->urls->generate('app_reset_password', ['token' => $token, '_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        $this->mailer->send($email);
    }
}
