<?php

namespace App\Tests\Command;

use App\Entity\ContactType;
use App\Entity\ContactWindow;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The console is the only place a role can be handed out today: registration always creates a
 * ROLE_USER account and the backoffice has no user screen yet.
 */
final class PromoteUserCommandTest extends KernelTestCase
{
    public function testItGrantsModeratorToARegisteredAccount(): void
    {
        $email = self::uniqueEmail();
        $this->createAccount($email);

        $tester = $this->promote($email, 'moderator');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $user = $this->users()->findOneByEmail($email);
        self::assertNotNull($user);
        self::assertContains(User::ROLE_MODERATOR, $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testItGrantsSuperAdmin(): void
    {
        $email = self::uniqueEmail();
        $this->createAccount($email);

        $tester = $this->promote($email, 'super_admin');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $user = $this->users()->findOneByEmail($email);
        self::assertNotNull($user);
        self::assertContains(User::ROLE_SUPER_ADMIN, $user->getRoles());
    }

    public function testItRefusesAnUnknownRoleAndLeavesTheAccountAlone(): void
    {
        $email = self::uniqueEmail();
        $this->createAccount($email);

        $tester = $this->promote($email, 'admin');

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('moderator, super_admin', $tester->getDisplay());

        $user = $this->users()->findOneByEmail($email);
        self::assertNotNull($user);
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testItFailsOnAnAccountThatDoesNotExist(): void
    {
        $email = self::uniqueEmail();

        $tester = $this->promote($email, 'moderator');

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertNull($this->users()->findOneByEmail($email));
    }

    private function promote(string $email, string $role): CommandTester
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:user:promote'));
        $tester->execute(['email' => $email, 'role' => $role]);

        return $tester;
    }

    private function createAccount(string $email): void
    {
        $now = new \DateTimeImmutable();
        $user = new User();
        $user
            ->setEmail($email)
            ->setContactName('Moderator')
            ->setContactType(ContactType::Phone)
            ->setContactWindow(ContactWindow::Morning)
            // This test never authenticates: the promotion does not look at the hash.
            ->setPassword('unused')
            // The columns are NOT NULL on purpose — an account always carries its consent record.
            ->acceptPolicy($now, '1.0')
            ->confirmAdulthood($now);

        $this->users()->save($user);
    }

    private function users(): UserRepository
    {
        return self::getContainer()->get(UserRepository::class);
    }

    private static function uniqueEmail(): string
    {
        return \sprintf('promote.%s@example.com', bin2hex(random_bytes(6)));
    }
}
