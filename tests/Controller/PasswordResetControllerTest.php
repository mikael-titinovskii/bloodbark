<?php

namespace App\Tests\Controller;

use App\Entity\ContactType;
use App\Entity\ContactWindow;
use App\Entity\PasswordResetRequest;
use App\Entity\User;
use App\Repository\PasswordResetRequestRepository;
use App\Repository\UserRepository;
use App\Service\PasswordResetService;
use App\Tests\Support\ClearsRateLimits;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Same rule as the registration tests: mail assertions only hold until the next request, because
 * the browser reboots the kernel and the mailer log with it.
 */
final class PasswordResetControllerTest extends WebTestCase
{
    use ClearsRateLimits;
    use MailerAssertionsTrait;

    private const string NEW_PASSWORD = 'Str0ng-Reset-Passphrase-2026';

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        self::clearRateLimits();
        // createClient() refuses to run with a kernel already booted.
        self::ensureKernelShutdown();
    }

    public function testTheRequestPageOpensInBothLocales(): void
    {
        $client = self::createClient();

        $client->request('GET', '/forgot-password');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[name="password_reset_request[email]"]');
        self::assertSelectorTextContains('h1', 'Resetează parola');

        $client->request('GET', '/ru/forgot-password');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Сброс пароля');
    }

    public function testAnUnknownAddressGetsTheSameAnswerAndNoMail(): void
    {
        $client = self::createClient();

        $client->submit($this->requestForm($client, self::uniqueEmail()));

        self::assertResponseRedirects('/forgot-password/sent');
        self::assertEmailCount(0);

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        // The sentence cannot depend on whether the address exists.
        self::assertSelectorTextContains('main', 'Dacă există un cont pentru această adresă');
    }

    public function testAKnownAddressGetsALinkByMail(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();
        $this->createAccount($email);

        $token = $this->requestLink($client, $email);

        $message = self::mailedMessage();
        self::assertSame($email, $message->getTo()[0]->getAddress());
        self::assertSame('Resetarea parolei Bloodbark', $message->getSubject());

        // The plaintext token is in the mail and nowhere else: the row keeps its hash.
        $request = $this->requests()->findOneBy(['user' => $this->users()->findOneByEmail($email)]);
        self::assertNotNull($request);
        self::assertSame(hash('sha256', $token), $request->getTokenHash());
        self::assertNotSame($token, $request->getTokenHash());
    }

    public function testOpeningTheLinkOnlyShowsTheForm(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();
        $this->createAccount($email);

        $token = $this->requestLink($client, $email);
        $before = $this->passwordHash($email);

        $client->request('GET', '/reset-password/'.$token);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main form');
        self::assertSelectorTextContains('h1', 'Alege o parolă nouă');
        // Mail clients and scanners open links on their own: a GET must not change anything.
        self::assertSame($before, $this->passwordHash($email));
    }

    public function testAPasswordThatIsRepeatedDifferentlyIsRejected(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();
        $this->createAccount($email);
        $token = $this->requestLink($client, $email);
        $before = $this->passwordHash($email);

        $form = $this->resetForm($client, $token);
        $client->submit($form->setValues([
            'reset_password[plainPassword][first]' => self::NEW_PASSWORD,
            'reset_password[plainPassword][second]' => self::NEW_PASSWORD.'-typo',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Parolele nu coincid.');
        self::assertSame($before, $this->passwordHash($email));
    }

    public function testATooShortPasswordIsRejected(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();
        $this->createAccount($email);
        $token = $this->requestLink($client, $email);
        $before = $this->passwordHash($email);

        $form = $this->resetForm($client, $token);
        $client->submit($form->setValues([
            'reset_password[plainPassword][first]' => 'short',
            'reset_password[plainPassword][second]' => 'short',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Parola este prea scurtă.');
        self::assertSame($before, $this->passwordHash($email));
    }

    public function testTheResetChangesThePasswordAndSpendsTheLink(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();
        $this->createAccount($email);
        $token = $this->requestLink($client, $email);

        $form = $this->resetForm($client, $token);
        $client->submit($form->setValues([
            'reset_password[plainPassword][first]' => self::NEW_PASSWORD,
            'reset_password[plainPassword][second]' => self::NEW_PASSWORD,
        ]));

        self::assertResponseRedirects('/login');

        $user = $this->users()->findOneByEmail($email);
        self::assertNotNull($user);
        self::assertTrue($this->hasher()->isPasswordValid($user, self::NEW_PASSWORD));
        self::assertFalse($this->hasher()->isPasswordValid($user, 'initial-Password-123'));

        // The login page carries the confirmation the reset set as a flash.
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Parola a fost schimbată');

        // One use only.
        $client->request('GET', '/reset-password/'.$token);
        self::assertResponseStatusCodeSame(410);
    }

    public function testTheNewPasswordSignsInAndTheOldOneDoesNot(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();
        $this->createAccount($email);
        $token = $this->requestLink($client, $email);

        $form = $this->resetForm($client, $token);
        $client->submit($form->setValues([
            'reset_password[plainPassword][first]' => self::NEW_PASSWORD,
            'reset_password[plainPassword][second]' => self::NEW_PASSWORD,
        ]));
        self::assertResponseRedirects('/login');

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('main form')->form([
            'email' => $email,
            'password' => self::NEW_PASSWORD,
        ]));
        self::assertResponseRedirects('/account');

        // Drop the session cookie instead of logging out: the point is an anonymous client.
        $client->getCookieJar()->clear();
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('main form')->form([
            'email' => $email,
            'password' => 'initial-Password-123',
        ]));
        self::assertResponseRedirects('/login');
    }

    public function testAnExpiredLinkIsGone(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();
        $user = $this->createAccount($email);

        $token = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable();
        $this->requests()->save(new PasswordResetRequest(
            $user,
            hash('sha256', $token),
            $now->modify('-3 hours'),
            $now->modify('-1 hour'),
        ));

        $client->request('GET', '/reset-password/'.$token);

        self::assertResponseStatusCodeSame(410);
        self::assertSelectorTextContains('h1', 'Linkul nu mai este valabil');
    }

    public function testAskingAgainRetiresThePreviousLink(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();
        $this->createAccount($email);

        $first = $this->requestLink($client, $email);
        $second = $this->requestLink($client, $email);

        self::assertNotSame($first, $second);

        $client->request('GET', '/reset-password/'.$first);
        self::assertResponseStatusCodeSame(410);

        $client->request('GET', '/reset-password/'.$second);
        self::assertResponseIsSuccessful();
    }

    public function testTheExpiryIsTheDocumentedTtl(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();
        $this->createAccount($email);

        $client->submit($this->requestForm($client, $email));
        self::assertResponseRedirects('/forgot-password/sent');

        $message = self::mailedMessage();
        self::assertStringContainsString(
            \sprintf('Linkul expiră în %d ore', PasswordResetService::TTL_HOURS),
            (string) $message->getTextBody(),
        );
    }

    /**
     * Same ceiling as registration, and for the same reason: this form sends mail.
     */
    public function testTheSixthResetRequestFromOneAddressIsRefused(): void
    {
        $client = self::createClient();

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $client->submit($this->requestForm($client, self::uniqueEmail()));
            self::assertResponseRedirects('/forgot-password/sent');
        }

        $client->submit($this->requestForm($client, self::uniqueEmail()));

        self::assertResponseStatusCodeSame(429);
        self::assertTrue($client->getResponse()->headers->has('Retry-After'));
    }

    private function requestForm(KernelBrowser $client, string $email): Form
    {
        $crawler = $client->request('GET', '/forgot-password');

        return $crawler->filter('main form')->form(['password_reset_request[email]' => $email]);
    }

    /**
     * Runs the "send me a link" half and digs the token out of the mailed URL.
     */
    private function requestLink(KernelBrowser $client, string $email): string
    {
        $client->submit($this->requestForm($client, $email));
        self::assertResponseRedirects('/forgot-password/sent');

        $body = (string) self::mailedMessage()->getTextBody();

        if (1 !== preg_match('#/reset-password/([0-9a-f]{64})#', $body, $matches)) {
            self::fail('The e-mail must carry a reset link.');
        }

        return $matches[1];
    }

    private function resetForm(KernelBrowser $client, string $token): Form
    {
        $crawler = $client->request('GET', '/reset-password/'.$token);
        self::assertResponseIsSuccessful();

        return $crawler->filter('main form')->form();
    }

    private static function mailedMessage(): Email
    {
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);

        return $message;
    }

    private function createAccount(string $email, string $plainPassword = 'initial-Password-123'): User
    {
        $now = new \DateTimeImmutable();
        $user = new User();
        $user
            ->setEmail($email)
            ->setContactName('Reset Owner')
            ->setContactType(ContactType::Phone)
            ->setContactWindow(ContactWindow::Evening)
            ->acceptPolicy($now, '1.0')
            ->confirmAdulthood($now);
        $user->setPassword($this->hasher()->hashPassword($user, $plainPassword));

        $this->users()->save($user);

        return $user;
    }

    private function passwordHash(string $email): ?string
    {
        return $this->users()->findOneByEmail($email)?->getPassword();
    }

    private function users(): UserRepository
    {
        return self::getContainer()->get(UserRepository::class);
    }

    private function requests(): PasswordResetRequestRepository
    {
        return self::getContainer()->get(PasswordResetRequestRepository::class);
    }

    private function hasher(): UserPasswordHasherInterface
    {
        return self::getContainer()->get(UserPasswordHasherInterface::class);
    }

    private static function uniqueEmail(): string
    {
        return \sprintf('reset.%s@example.com', bin2hex(random_bytes(6)));
    }
}
