<?php

namespace App\Tests\Smoke;

use App\Tests\Support\SmokeTester;

final class HomepageCest
{
    public function opensInBothLocales(SmokeTester $I): void
    {
        $I->amOnPage('/');
        $I->seeResponseCodeIs(200);

        $I->amOnPage('/ru');
        $I->seeResponseCodeIs(200);
    }

    public function refusesAnUnsupportedLocale(SmokeTester $I): void
    {
        $I->amOnPage('/en');
        $I->seeResponseCodeIs(404);
    }
}
