<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Support\ClearsRateLimits;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\Mime\Email;

/**
 * `SUPERADMIN_EMAIL` is `superadmin@example.test` in phpunit.dist.xml. The value is fixed and the
 * test database outlives a run, so the promotion test deletes that account before registering it
 * again.
 */
final class EmailConfirmationTest extends WebTestCase
{
    use ClearsRateLimits;
    use MailerAssertionsTrait;

    private const string SUPERADMIN_EMAIL = 'superadmin@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        self::clearRateLimits();
        // createClient() refuses to run with a kernel already booted.
        self::ensureKernelShutdown();
    }

    public function testRegistrationLeavesTheAddressUnconfirmed(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();

        $client->submit($this->registrationForm($client, $email));

        self::assertResponseRedirects('/register/sent');
        self::assertStringContainsString('/confirm-email/', (string) self::mailedMessage()->getTextBody());

        $user = $this->users()->findOneByEmail($email);
        self::assertNotNull($user);
        self::assertFalse($user->isConfirmed());
        self::assertNull($user->getConfirmedAt());
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testFollowingTheLinkConfirmsTheAddress(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();
        $client->submit($this->registrationForm($client, $email));
        $token = self::tokenFrom(self::mailedMessage());

        $client->request('GET', '/confirm-email/'.$token);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Adresa a fost confirmată');
        self::assertSelectorTextContains('main', $email);

        $user = $this->users()->findOneByEmail($email);
        self::assertNotNull($user);
        self::assertTrue($user->isConfirmed());
        self::assertNull($user->getConfirmationTokenHash());
        // An ordinary address stays a plain user after confirming.
        self::assertNotContains(User::ROLE_SUPER_ADMIN, $user->getRoles());
    }

    public function testTheConfiguredAddressIsPromotedOnlyByConfirming(): void
    {
        $client = self::createClient();
        $this->forgetAccount(self::SUPERADMIN_EMAIL);

        $client->submit($this->registrationForm($client, self::SUPERADMIN_EMAIL));

        $registered = $this->users()->findOneByEmail(self::SUPERADMIN_EMAIL);
        self::assertNotNull($registered);
        // Typing the address proves nothing: no role until the mailbox answers.
        self::assertSame(['ROLE_USER'], $registered->getRoles());

        $token = self::tokenFrom(self::mailedMessage());
        $client->request('GET', '/confirm-email/'.$token);

        self::assertResponseIsSuccessful();

        $promoted = $this->users()->findOneByEmail(self::SUPERADMIN_EMAIL);
        self::assertNotNull($promoted);
        self::assertTrue($promoted->isConfirmed());
        // Only the stored role: the super admin ⊃ moderator hierarchy is applied by the firewall.
        self::assertSame([User::ROLE_SUPER_ADMIN, 'ROLE_USER'], $promoted->getRoles());
    }

    public function testAConfirmedAccountReachesItsAccountPage(): void
    {
        $client = self::createClient();
        $this->forgetAccount(self::SUPERADMIN_EMAIL);
        $client->submit($this->registrationForm($client, self::SUPERADMIN_EMAIL));
        $token = self::tokenFrom(self::mailedMessage());
        $client->request('GET', '/confirm-email/'.$token);

        $user = $this->users()->findOneByEmail(self::SUPERADMIN_EMAIL);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request('GET', '/account');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Da');
    }

    public function testAUsedOrUnknownLinkIsGone(): void
    {
        $client = self::createClient();

        $client->request('GET', '/confirm-email/'.str_repeat('a', 64));
        self::assertResponseStatusCodeSame(410);
        self::assertSelectorTextContains('h1', 'Linkul nu mai este valabil');

        $client->submit($this->registrationForm($client, self::uniqueEmail()));
        $token = self::tokenFrom(self::mailedMessage());

        $client->request('GET', '/confirm-email/'.$token);
        self::assertResponseIsSuccessful();

        // One use only: the confirmation itself clears the token.
        $client->request('GET', '/confirm-email/'.$token);
        self::assertResponseStatusCodeSame(410);
    }

    public function testTheAccountPageCanSendANewLink(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();
        $client->submit($this->registrationForm($client, $email));
        $first = self::tokenFrom(self::mailedMessage());

        $user = $this->users()->findOneByEmail($email);
        self::assertNotNull($user);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/account');
        self::assertSelectorTextContains('main', 'Nu încă');
        $client->submit($crawler->filter('form[action="/account/confirmation"]')->form());

        self::assertResponseRedirects('/account');
        // Read the message before following the redirect: the next request clears the mailer log.
        $second = self::tokenFrom(self::mailedMessage());
        self::assertNotSame($first, $second);

        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Ți-am trimis un link nou');

        // The old link died with the new one.
        $client->getCookieJar()->clear();
        $client->request('GET', '/confirm-email/'.$first);
        self::assertResponseStatusCodeSame(410);
    }

    private function registrationForm(KernelBrowser $client, string $email): Form
    {
        $crawler = $client->request('GET', '/register');
        $form = $crawler->filter('main form')->form();
        $form->setValues([
            'registration[email]' => $email,
            'registration[contactName]' => 'Confirm Owner',
            'registration[contactType]' => 'phone',
            'registration[contactWindow]' => 'evening',
            'registration[consent]' => true,
            'registration[adult]' => true,
        ]);

        return $form;
    }

    private static function tokenFrom(Email $message): string
    {
        $body = (string) $message->getTextBody();

        if (1 !== preg_match('#/confirm-email/([0-9a-f]{64})#', $body, $matches)) {
            self::fail('The e-mail must carry a confirmation link.');
        }

        return $matches[1];
    }

    private static function mailedMessage(): Email
    {
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);

        return $message;
    }

    private function forgetAccount(string $email): void
    {
        $this->entityManager()
            ->createQuery('DELETE FROM '.User::class.' u WHERE u.email = :email')
            ->setParameter('email', $email)
            ->execute();
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function users(): UserRepository
    {
        return self::getContainer()->get(UserRepository::class);
    }

    private static function uniqueEmail(): string
    {
        return \sprintf('confirm.%s@example.com', bin2hex(random_bytes(6)));
    }
}
