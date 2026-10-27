<?php

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

final class AnalyticsConsentCest
{
    public function consentStartsOnTheLeftAndWithdrawalDeletesAnalytics(AcceptanceTester $I): void
    {
        $I->amOnPage('/');
        $I->waitForElementVisible('#cc-main .cm--left.cm--bottom', 10);
        $I->see('Cookie-uri și statistici de utilizare');
        $I->assertSame(16, (int) $I->executeJS(
            'return Math.round(document.querySelector("#cc-main .cm").getBoundingClientRect().left);',
        ));

        $I->click('//div[@id="cc-main"]//button[contains(@class, "cm__btn") and contains(., "Accept statistici")]');
        $I->wait(1);
        $I->seeCookie('bb_consent');

        $I->amOnPage('/login');
        $I->fillField(['name' => 'email'], 'superadmin@bloodbark.test');
        $I->fillField(['name' => 'password'], 'superadmin-dev-2026');
        $I->click('//main//button[@type="submit"]');
        $I->waitForText('Contul meu', 10, 'main h1');
        $I->amOnPage('/ro/admin/analytics');
        $I->see('page_view');

        $I->amOnPage('/');
        $I->click('//footer//button[@data-cc="show-preferencesModal"]');
        $I->waitForElementVisible('#cc-main .pm--left', 10);
        $I->click('//div[@id="cc-main"]//div[contains(@class, "pm__footer")]//button[contains(., "Doar cookie-uri necesare")]');
        $I->wait(1);

        $I->amOnPage('/ro/admin/analytics');
        $I->dontSee('page_view');
        $I->see('Nu există evenimente în această perioadă.');
    }
}
