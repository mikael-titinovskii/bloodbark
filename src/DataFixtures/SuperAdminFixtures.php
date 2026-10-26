<?php

namespace App\DataFixtures;

use App\Entity\ContactType;
use App\Entity\ContactWindow;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The account you sign in with while developing, so `/admin` is reachable without registering,
 * reading the generated password out of Mailpit and promoting yourself.
 *
 * The password is written down here on purpose — that is what makes it usable — so this class must
 * never reach production. `DoctrineFixturesBundle` is registered for `dev` and `test` only in
 * config/bundles.php, which means `doctrine:fixtures:load` does not exist in a production
 * container and this class is never instantiated there.
 *
 * It is a fully formed account (consent, adulthood, confirmed address), not a bare row, so the
 * account page and the backoffice show the same shape a real registration produces.
 */
final class SuperAdminFixtures extends Fixture
{
    public const string EMAIL = 'superadmin@bloodbark.test';
    public const string PASSWORD = 'superadmin-dev-2026';

    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        #[Autowire('%app.legal.policy_version%')] private readonly string $policyVersion,
    ) {
    }

    /**
     * Idempotent: `doctrine:fixtures:load --append` runs against a database that may already hold
     * the row, and the unique index on the address would turn that into a failure.
     */
    public function load(ObjectManager $manager): void
    {
        if (null !== $this->users->findOneByEmail(self::EMAIL)) {
            return;
        }

        $now = new \DateTimeImmutable();

        $admin = (new User())
            ->setEmail(self::EMAIL)
            ->setContactName('Super Admin')
            ->setContactType(ContactType::Other)
            ->setContactWindow(ContactWindow::NoPreference)
            ->addRole(User::ROLE_SUPER_ADMIN)
            ->acceptPolicy($now, $this->policyVersion)
            ->confirmAdulthood($now)
            ->confirm($now);

        $admin->setPassword($this->hasher->hashPassword($admin, self::PASSWORD));

        $manager->persist($admin);
        $manager->flush();
    }
}
