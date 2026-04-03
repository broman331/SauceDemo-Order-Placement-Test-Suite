<?php

declare(strict_types=1);

namespace SauceDemo\Tests;

use SauceDemo\Config;

/**
 * Regression tests for the login flow.
 *
 * TC001 — Successful login (standard_user)
 * TC002 — Login with invalid credentials shows an error
 * TC003 — Locked-out user cannot log in
 */
class LoginTest extends BaseTest
{
    /**
     * TC001
     * Given valid credentials for standard_user
     * When the user submits the login form
     * Then they land on the inventory page
     */
    public function testSuccessfulLogin(): void
    {
        $inventoryPage = $this->loginPage
            ->loginAs(Config::STANDARD_USER, Config::PASSWORD)
            ->waitUntilLoaded();

        $this->assertTrue(
            $inventoryPage->isOnInventoryPage(),
            'Expected to land on /inventory.html after a successful login'
        );
    }

    /**
     * TC002
     * Given an unknown username and a wrong password
     * When the user submits the login form
     * Then a visible error message is displayed and the user stays on the login page
     */
    public function testLoginWithInvalidCredentials(): void
    {
        $this->loginPage
            ->open()
            ->enterUsername(Config::INVALID_USER)
            ->enterPassword(Config::INVALID_PASS)
            ->clickLogin();

        $this->assertTrue(
            $this->loginPage->isErrorVisible(),
            'Expected an error message to appear after submitting invalid credentials'
        );

        $this->assertStringContainsString(
            'Username and password do not match',
            $this->loginPage->getErrorMessage(),
            'Error message text should indicate mismatched credentials'
        );
    }

    /**
     * TC003
     * Given the locked_out_user account
     * When the user submits the login form
     * Then a lockout error is displayed and the user remains on the login page
     */
    public function testLockedOutUserCannotLogin(): void
    {
        $this->loginPage
            ->open()
            ->enterUsername(Config::LOCKED_USER)
            ->enterPassword(Config::PASSWORD)
            ->clickLogin();

        $this->assertTrue(
            $this->loginPage->isErrorVisible(),
            'Expected an error message for the locked-out user'
        );

        $this->assertStringContainsString(
            'locked out',
            strtolower($this->loginPage->getErrorMessage()),
            'Error message should mention that the account is locked'
        );

        $this->assertTrue(
            $this->loginPage->isOnLoginPage(),
            'Locked-out user should remain on the login page'
        );
    }
}
