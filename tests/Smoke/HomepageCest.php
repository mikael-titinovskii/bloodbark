<?php

namespace App\Tests\Smoke;

use App\Tests\Support\SmokeTester;

final class HomepageCest
{
    public function opens(SmokeTester $I): void
    {
        $I->amOnPage('/');
        $I->seeResponseCodeIs(200);
    }
}
