<?php

namespace App\Service;

use App\Entity\User;
use App\Mailer\RegistrationMailer;
use App\Repository\UserRepository;
use App\Security\PasswordGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Turns a validated registration form into a stored account with a password only the user's inbox
 * knows.
 *
 * The account starts unconfirmed: the address is confirmed by following the link in the same
 * message, and that is what can make it a super admin (see EmailConfirmationService).
 */
final readonly class RegistrationService
{
    public function __construct(
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
        private PasswordGenerator $generator,
        private RegistrationMailer $mailer,
        private EmailConfirmationService $confirmations,
        #[Autowire('%app.legal.policy_version%')] private string $policyVersion,
    ) {
    }

    public function register(User $user, string $locale): void
    {
        $password = $this->generator->generate();
        $now = new \DateTimeImmutable();

        $user
            ->setPassword($this->hasher->hashPassword($user, $password))
            ->acceptPolicy($now, $this->policyVersion)
            ->confirmAdulthood($now);

        $confirmationToken = $this->confirmations->start($user);

        // Order matters: the entity is queued but not flushed until the mail is away. If the
        // transport fails the account is never created, so the address stays free to register
        // with again instead of being taken by an account whose password nobody received.
        $this->users->save($user, flush: false);
        $this->mailer->send($user, $password, $confirmationToken, $locale);
        $this->users->save($user);
    }
}
