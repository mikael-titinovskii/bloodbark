<?php

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

final class HomepageCest
{
    public function rendersInRomanian(AcceptanceTester $I): void
    {
        $I->amOnPage('/');
        $I->seeInTitle('Bloodbark');
        $I->see('Suntem un serviciu de informare');
        $I->see('Nu suntem un serviciu de urgență.');
    }

    public function switchesToRussianAndStaysThere(AcceptanceTester $I): void
    {
        $I->amOnPage('/');
        $I->click('Русский');
        $I->seeInCurrentUrl('/ru');
        $I->see('Мы — информационный сервис');
        $I->see('Мы не служба экстренной помощи.');
    }
}
