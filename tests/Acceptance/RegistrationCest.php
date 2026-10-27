<?php

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

final class RegistrationCest
{
    public function registersAndIsToldToCheckTheMail(AcceptanceTester $I): void
    {
        $I->amOnPage('/register');
        $I->fillField(['name' => 'registration[email]'], 'e2e@example.com');
        $I->fillField(['name' => 'registration[contactName]'], 'E2E Owner');
        $I->selectOption(['name' => 'registration[contactType]'], 'whatsapp');
        $I->selectOption(['name' => 'registration[contactWindow]'], 'evening');
        $I->checkOption(['name' => 'registration[consent]']);
        $I->checkOption(['name' => 'registration[adult]']);
        $I->click('//main//button[@type="submit"]');

        $I->seeInCurrentUrl('/register/sent');
        $I->see('Verifică-ți e-mailul');
        $I->see('e2e@example.com');
    }

    /**
     * A missing checkbox never reaches the server — the browser blocks the submit on the input's
     * `required` attribute — so the server-side refusal is exercised through an address that is
     * already taken, which no client-side check can catch.
     */
    public function refusesAnAddressThatAlreadyHasAnAccount(AcceptanceTester $I): void
    {
        $this->signUp($I, 'e2e.duplicate@example.com');
        $I->waitForText('Verifică-ți e-mailul', 10);

        $this->signUp($I, 'e2e.duplicate@example.com');
        $I->waitForText('Există deja un cont cu această adresă', 10);
    }

    private function signUp(AcceptanceTester $I, string $email): void
    {
        $I->amOnPage('/register');
        $I->fillField(['name' => 'registration[email]'], $email);
        $I->fillField(['name' => 'registration[contactName]'], 'E2E Owner');
        $I->selectOption(['name' => 'registration[contactType]'], 'phone');
        $I->selectOption(['name' => 'registration[contactWindow]'], 'morning');
        $I->checkOption(['name' => 'registration[consent]']);
        $I->checkOption(['name' => 'registration[adult]']);
        $I->click('//main//button[@type="submit"]');
    }
}
