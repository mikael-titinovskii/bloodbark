<?php

namespace App\Service;

use App\Entity\PasswordResetRequest;
use App\Mailer\PasswordResetMailer;
use App\Repository\PasswordResetRequestRepository;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * "I forgot my password" in two halves: asking for a link, and spending it.
 *
 * Asking never says whether the address has an account — the caller answers the same way either
 * way, so the form cannot be used to find out who is registered.
 */
final readonly class PasswordResetService
{
    /** How long a link stays usable. Short on purpose: it is read from a mailbox, not remembered. */
    public const int TTL_HOURS = 2;

    public function __construct(
        private PasswordResetRequestRepository $requests,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
        private PasswordResetMailer $mailer,
    ) {
    }

    public function request(string $email, string $locale): void
    {
        $user = $this->users->findOneByEmail($email);

        if (null === $user) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable();

        // One live link per account: asking again must retire the previous link rather than add a
        // second working one to the same mailbox.
        $this->requests->deleteFor($user);

        $request = new PasswordResetRequest(
            $user,
            self::hashToken($token),
            $now,
            $now->modify(\sprintf('+%d hours', self::TTL_HOURS)),
        );

        // Same order as registration: the row is queued but only flushed once the mail is out, so
        // a failing transport does not consume the link the user never received.
        $this->requests->save($request, flush: false);
        $this->mailer->sendLink($user, $token, $locale, self::TTL_HOURS);
        $this->requests->save($request);
    }

    public function findUsable(string $token): ?PasswordResetRequest
    {
        return $this->requests->findUsable(self::hashToken($token), new \DateTimeImmutable());
    }

    /**
     * Sets the new password and spends the link in one flush. The changed hash is also what logs the
     * account's other sessions out: the firewall compares it against the one it stored at login.
     */
    public function reset(PasswordResetRequest $request, string $plainPassword): void
    {
        $user = $request->getUser();
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));

        $this->requests->remove($request, flush: false);
        $this->users->save($user);
    }

    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
