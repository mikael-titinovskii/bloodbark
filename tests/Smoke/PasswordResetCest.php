<?php

namespace App\Tests\Smoke;

use App\Tests\Support\SmokeTester;

final class PasswordResetCest
{
    public function opensInBothLocales(SmokeTester $I): void
    {
        $I->amOnPage('/forgot-password');
        $I->seeResponseCodeIs(200);
        $I->see('Resetează parola');

        $I->amOnPage('/ru/forgot-password');
        $I->seeResponseCodeIs(200);
        $I->see('Сброс пароля');
    }

    public function answersTheSameWayForAnUnknownAddress(SmokeTester $I): void
    {
        $I->amOnPage('/forgot-password');
        $I->fillField(['name' => 'password_reset_request[email]'], 'nobody@example.com');
        $I->click('//main//button[@type="submit"]');

        $I->seeInCurrentUrl('/forgot-password/sent');
        $I->see('Dacă există un cont pentru această adresă');
    }

    public function refusesALinkThatWasNeverIssued(SmokeTester $I): void
    {
        $I->amOnPage('/reset-password/'.str_repeat('a', 64));

        $I->seeResponseCodeIs(410);
        $I->see('Linkul nu mai este valabil');
    }
}
