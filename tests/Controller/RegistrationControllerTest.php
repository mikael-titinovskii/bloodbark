<?php

namespace App\Tests\Controller;

use App\Entity\ContactType;
use App\Entity\ContactWindow;
use App\Repository\UserRepository;
use App\Security\PasswordGenerator;
use App\Tests\Support\ClearsRateLimits;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Mail assertions only hold until the next request: the browser reboots the kernel between
 * requests, so each one starts with an empty mailer log. Everything about the message is
 * therefore read right after the request that sent it.
 */
final class RegistrationControllerTest extends WebTestCase
{
    use ClearsRateLimits;
    use MailerAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();

        // Limiter counters live in a cache pool that outlives a test and every test arrives from
        // 127.0.0.1; without this the suite would throttle itself.
        self::bootKernel();
        self::clearRateLimits();
        // createClient() refuses to run against an already booted kernel.
        self::ensureKernelShutdown();
    }

    public function testTheFormOpensInBothLocales(): void
    {
        $client = self::createClient();

        $client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[name="registration[email]"]');
        self::assertSelectorExists('form select[name="registration[contactType]"]');
        self::assertSelectorExists('form select[name="registration[contactWindow]"]');
        self::assertSelectorTextContains('h1', 'Creează un cont');
        self::assertSelectorTextContains('form', 'Confirm că am împlinit 18 ani.');
        // The consent line names the version the record will carry.
        self::assertSelectorTextContains('form', 'versiunea 1.0');

        $client->request('GET', '/ru/register');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Создать аккаунт');
        self::assertSelectorTextContains('form', 'версия 1.0');
    }

    public function testRegisteringStoresTheAccountAndMailsThePassword(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();

        $client->submit($this->registrationForm($client, $email, 'Ion Popescu'));

        self::assertResponseRedirects('/register/sent');

        self::assertEmailCount(1);
        $message = self::mailedMessage();
        self::assertSame($email, $message->getTo()[0]->getAddress());
        self::assertSame('Contul tău Bloodbark', $message->getSubject());

        $user = $this->users()->findOneByEmail($email);
        self::assertNotNull($user);
        self::assertSame($email, $user->getEmail());
        self::assertSame('Ion Popescu', $user->getContactName());
        self::assertSame(ContactType::WhatsApp, $user->getContactType());
        self::assertSame(ContactWindow::Evening, $user->getContactWindow());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertSame('1.0', $user->getConsentPolicyVersion());
        self::assertNotNull($user->getConsentAt());
        self::assertNotNull($user->getAdultConfirmedAt());

        $password = self::passwordFrom($message);
        // The plaintext lives only in the e-mail: what the row holds is the hash.
        self::assertNotSame($password, $user->getPassword());
        self::assertTrue($this->hasher()->isPasswordValid($user, $password));

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Verifică-ți e-mailul');
        self::assertSelectorTextContains('main', $email);
    }

    public function testSuccessfulRegistrationIsRecordedWithAnalyticsConsent(): void
    {
        $client = self::createClient();
        $consentId = sprintf('00000000-0000-4000-a000-%012s', bin2hex(random_bytes(6)));
        $client->request('POST', '/analytics/consent', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'consentId' => $consentId,
            'analyticsAllowed' => true,
            'revision' => 1,
        ], JSON_THROW_ON_ERROR));
        $client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('bb_consent', json_encode([
            'consentId' => $consentId,
            'categories' => ['necessary', 'analytics'],
            'revision' => 1,
        ], JSON_THROW_ON_ERROR)));

        $client->submit($this->registrationForm($client, self::uniqueEmail(), 'Ion Popescu'));

        self::assertResponseRedirects('/register/sent');
        $consent = self::getContainer()->get(\App\Repository\AnalyticsConsentRepository::class)->findOneByConsentId($consentId);
        self::assertNotNull($consent);
        $event = self::getContainer()->get(\App\Repository\AnalyticsEventRepository::class)->findOneBy([
            'name' => 'registration_completed',
            'consent' => $consent,
        ]);
        self::assertNotNull($event);
        self::assertSame(['locale' => 'ro'], $event->getProperties());
    }

    public function testTheMailedPasswordSignsIn(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();

        $client->submit($this->registrationForm($client, $email, 'Ion Popescu'));
        $password = self::passwordFrom(self::mailedMessage());

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('main form')->form([
            'email' => $email,
            'password' => $password,
        ]));

        self::assertResponseRedirects('/account');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Ion Popescu');
        self::assertSelectorTextContains('main', 'WhatsApp');
    }

    public function testNothingIsCreatedWithoutConsent(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();

        $form = $this->registrationForm($client, $email, 'Ion Popescu');
        $form->setValues(['registration[consent]' => false]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Fără acordul pentru prelucrarea datelor');
        self::assertEmailCount(0);
        self::assertNull($this->users()->findOneByEmail($email));
    }

    public function testNothingIsCreatedWithoutTheAgeGate(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();

        $form = $this->registrationForm($client, $email, 'Ion Popescu');
        $form->setValues(['registration[adult]' => false]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'doar de persoane de 18 ani');
        self::assertEmailCount(0);
        self::assertNull($this->users()->findOneByEmail($email));
    }

    public function testAnAddressCanOnlyRegisterOnce(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();

        $client->submit($this->registrationForm($client, $email, 'Ion Popescu'));
        self::assertSame(1, $this->users()->count(['email' => $email]));

        // Same address, different casing: addresses are stored lowercased, so this must collide.
        $client->submit($this->registrationForm($client, mb_strtoupper($email), 'Alt Ion'));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Există deja un cont cu această adresă');
        self::assertSame(1, $this->users()->count(['email' => $email]));
        self::assertEmailCount(0);
    }

    public function testARussianRegistrationGetsARussianEmail(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();

        $client->submit($this->registrationForm($client, $email, 'Ион', 'ru'));

        self::assertResponseRedirects('/ru/register/sent');

        $message = self::mailedMessage();
        self::assertSame('Ваш аккаунт Bloodbark', $message->getSubject());
        self::assertStringContainsString('Здравствуйте', (string) $message->getTextBody());
        self::assertStringContainsString('/ru/login', (string) $message->getTextBody());
    }

    public function testTheAccountPageNeedsSigningIn(): void
    {
        $client = self::createClient();

        $client->request('GET', '/account');
        self::assertResponseRedirects('/login');

        $client->request('GET', '/ru/account');
        self::assertResponseRedirects('/ru/login');
    }

    public function testASignedInVisitorIsSentToTheAccountInsteadOfTheForm(): void
    {
        $client = self::createClient();
        $email = self::uniqueEmail();

        $client->submit($this->registrationForm($client, $email, 'Ion Popescu'));
        $password = self::passwordFrom(self::mailedMessage());

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('main form')->form([
            'email' => $email,
            'password' => $password,
        ]));

        $client->request('GET', '/register');
        self::assertResponseRedirects('/account');
    }

    /**
     * Five successful sign-ups an hour per address, so a loop cannot use the form as a mail relay.
     */
    public function testTheSixthSubmissionFromOneAddressIsRefused(): void
    {
        $client = self::createClient();

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $client->submit($this->registrationForm($client, self::uniqueEmail(), 'Throttled'));
            self::assertResponseRedirects('/register/sent');
        }

        $client->submit($this->registrationForm($client, self::uniqueEmail(), 'Throttled'));

        self::assertResponseStatusCodeSame(429);
        self::assertTrue($client->getResponse()->headers->has('Retry-After'));
    }

    private function registrationForm(KernelBrowser $client, string $email, string $contactName, string $locale = 'ro'): Form
    {
        $crawler = $client->request('GET', 'ru' === $locale ? '/ru/register' : '/register');
        $form = $crawler->filter('main form')->form();
        $form->setValues([
            'registration[email]' => $email,
            'registration[contactName]' => $contactName,
            'registration[contactType]' => ContactType::WhatsApp->value,
            'registration[contactWindow]' => ContactWindow::Evening->value,
            'registration[consent]' => true,
            'registration[adult]' => true,
        ]);

        return $form;
    }

    /**
     * Digs the generated password out of the text body. The alphabet is unambiguous and the
     * boundaries keep the match from being a slice of the address or of the login URL.
     */
    private static function passwordFrom(Email $message): string
    {
        $pattern = '/(?<![A-Za-z0-9])['.preg_quote(PasswordGenerator::ALPHABET, '/').']{'.PasswordGenerator::LENGTH.'}(?![A-Za-z0-9])/';

        self::assertSame(1, preg_match($pattern, (string) $message->getTextBody(), $matches), 'The e-mail must carry the generated password.');

        return $matches[0];
    }

    private static function mailedMessage(): Email
    {
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);

        return $message;
    }

    private function users(): UserRepository
    {
        return self::getContainer()->get(UserRepository::class);
    }

    private function hasher(): UserPasswordHasherInterface
    {
        return self::getContainer()->get(UserPasswordHasherInterface::class);
    }

    private static function uniqueEmail(): string
    {
        return \sprintf('ion.%s@example.com', bin2hex(random_bytes(6)));
    }
}
