<?php

namespace App\Tests\Smoke;

use App\Tests\Support\SmokeTester;

final class RegistrationCest
{
    public function opensInBothLocales(SmokeTester $I): void
    {
        $I->amOnPage('/register');
        $I->seeResponseCodeIs(200);
        $I->see('Creează un cont');

        $I->amOnPage('/ru/register');
        $I->seeResponseCodeIs(200);
        $I->see('Создать аккаунт');
    }

    public function keepsTheAccountPageBehindTheLogin(SmokeTester $I): void
    {
        $I->amOnPage('/account');
        $I->seeInCurrentUrl('/login');
        $I->see('Autentificare');
    }
}
