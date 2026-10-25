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
 * Delivers the generated password. This message is the only place the plaintext password ever
 * exists: the account stores the hash, nothing logs the plain value.
 */
final readonly class RegistrationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        #[Autowire('%app.mailer.sender%')] private string $senderAddress,
        #[Autowire('%app.mailer.sender_name%')] private string $senderName,
    ) {
    }

    public function send(User $user, string $plainPassword, string $locale): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->senderAddress, $this->senderName))
            ->to($user->getEmail())
            ->subject($this->translator->trans('registration.email.subject', locale: $locale))
            // BodyRenderer switches the translator to this locale for the whole template, so the
            // body is translated even when the mail is built outside a request.
            ->locale($locale)
            ->htmlTemplate('emails/registration.html.twig')
            ->textTemplate('emails/registration.txt.twig')
            ->context([
                'address' => $user->getEmail(),
                'password' => $plainPassword,
                'locale' => $locale,
                'loginUrl' => $this->urls->generate('app_login', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        $this->mailer->send($email);
    }
}
