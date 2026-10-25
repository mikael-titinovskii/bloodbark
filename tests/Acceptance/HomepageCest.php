<?php

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

final class HomepageCest
{
    public function renders(AcceptanceTester $I): void
    {
        $I->amOnPage('/');
        $I->seeInTitle('Bloodbark');
        $I->see('We are an information and matching service.');
        $I->see('Not an emergency service.');
    }
}
