<?php

namespace App\Tests\Controller;

use App\Entity\ContactType;
use App\Entity\ContactWindow;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * `/admin` is closed by `access_control`, so what is worth checking is the three refusals and the
 * one account that gets through. A moderator is refused deliberately: approving listings and
 * running the backoffice are different jobs.
 *
 * The backoffice is the only part of the site whose locale is a mandatory path segment, which is
 * what makes EasyAdmin's language switcher work; the tests therefore address `/ro/admin` and
 * `/ru/admin` rather than `/admin`.
 *
 * The page tests read the rendered labels, which is also the assertion that the `admin.*` keys
 * resolved — a missing key renders as the raw key, not as a word.
 */
final class AdminTest extends WebTestCase
{
    public function testTheOldAddressRedirectsToTheDefaultLocale(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin');

        self::assertResponseRedirects('/ro/admin');
    }

    public function testAnAnonymousVisitorIsSentToTheLoginPage(): void
    {
        $client = self::createClient();

        $client->request('GET', '/ro/admin');

        self::assertResponseRedirects('/login');
    }

    public function testAnOrdinaryAccountIsForbidden(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createUser());

        $client->request('GET', '/ro/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAModeratorIsForbidden(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createUser([User::ROLE_MODERATOR]));

        $client->request('GET', '/ro/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testTheAdminLandsOnTheAccountList(): void
    {
        $client = self::createClient();
        $admin = $this->createUser([User::ROLE_SUPER_ADMIN]);
        $client->loginUser($admin);

        $client->request('GET', '/ro/admin');
        self::assertResponseRedirects('/ro/admin/user');

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        // Menu label from `admin.menu.users`, role label from `admin.role.super_admin`.
        self::assertSelectorTextContains('body', 'Utilizatori');
        self::assertSelectorTextContains('table', $admin->getEmail());
        self::assertSelectorTextContains('table', 'Super administrator');
    }

    public function testTheSwitcherAddressServesTheSameListInRussian(): void
    {
        $client = self::createClient();
        $admin = $this->createUser([User::ROLE_SUPER_ADMIN]);
        $client->loginUser($admin);

        $client->request('GET', '/ru/admin/user');

        self::assertResponseIsSuccessful();
        // The other catalog, reached by the URL the language switcher builds.
        self::assertSelectorTextContains('body', 'Пользователи');
        self::assertSelectorTextContains('table', 'Супер-администратор');
    }

    public function testTheEditPageShowsWhyTheAddressCannotBeChanged(): void
    {
        $client = self::createClient();
        $user = $this->createUser();
        $client->loginUser($this->createUser([User::ROLE_SUPER_ADMIN]));

        $id = $user->getId();
        self::assertNotNull($id);

        $client->request('GET', '/ro/admin/user/'.$id.'/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Adresa este identificatorul de autentificare');
    }

    public function testAccountsCannotBeCreatedFromTheBackoffice(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createUser([User::ROLE_SUPER_ADMIN]));

        $client->request('GET', '/ro/admin/user/new');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(array $roles = []): User
    {
        $user = (new User())
            ->setEmail(\sprintf('admin-test-%s@example.test', bin2hex(random_bytes(8))))
            ->setPassword('not-a-hash-a-session-does-not-need')
            ->setContactName('Moderare Test')
            ->setContactType(ContactType::Phone)
            ->setContactWindow(ContactWindow::Evening);

        foreach ($roles as $role) {
            $user->addRole($role);
        }

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
