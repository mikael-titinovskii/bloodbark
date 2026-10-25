<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Proves that whoever registered controls the mailbox, and grants ROLE_SUPER_ADMIN at that moment
 * when the address is the one configured in `SUPERADMIN_EMAIL`.
 *
 * The promotion hangs off confirmation rather than off the registration itself: registering only
 * proves that somebody typed an address, and the address that owns the backoffice must not be
 * taken by whoever types it first.
 */
final readonly class EmailConfirmationService
{
    public function __construct(
        private UserRepository $users,
        #[Autowire('%app.security.super_admin_email%')] private string $superAdminEmail,
    ) {
    }

    /**
     * Generates the token, stores its hash and hands the plain value back for the e-mail. The
     * caller persists — registration flushes the account and the token in one write.
     */
    public function start(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $user->startConfirmation(self::hashToken($token));

        return $token;
    }

    /**
     * The same, for an account that already exists: replaces the link and saves it.
     */
    public function resend(User $user): string
    {
        $token = $this->start($user);
        $this->users->save($user);

        return $token;
    }

    /**
     * Confirms the address behind the token. Null when the token matches nothing — already used, or
     * superseded by a later one.
     */
    public function confirm(string $token): ?User
    {
        $user = $this->users->findByConfirmationToken(self::hashToken($token));

        if (null === $user) {
            return null;
        }

        $user->confirm(new \DateTimeImmutable());

        if ($this->isConfiguredSuperAdmin($user)) {
            $user->addRole(User::ROLE_SUPER_ADMIN);
        }

        $this->users->save($user);

        return $user;
    }

    private function isConfiguredSuperAdmin(User $user): bool
    {
        // The configured value is typed by hand in an env file; addresses are stored lowercased.
        $configured = mb_strtolower(trim($this->superAdminEmail));

        return '' !== $configured && $configured === $user->getEmail();
    }

    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
