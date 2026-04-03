<?php

declare(strict_types=1);

namespace SauceDemo\Pages;

use SauceDemo\Config;

/**
 * Page Object for the SauceDemo login page (/).
 */
class LoginPage extends BasePage
{
    public function open(): static
    {
        $this->driver->get(Config::baseUrl() . '/');
        $this->waitForVisible(Config::SEL_LOGIN_BUTTON);

        return $this;
    }

    public function enterUsername(string $username): static
    {
        $this->waitForElement(Config::SEL_USERNAME)->clear()->sendKeys($username);

        return $this;
    }

    public function enterPassword(string $password): static
    {
        $this->waitForElement(Config::SEL_PASSWORD)->clear()->sendKeys($password);

        return $this;
    }

    public function clickLogin(): static
    {
        $this->waitAndClick(Config::SEL_LOGIN_BUTTON);

        return $this;
    }

    /**
     * Convenience shorthand: open page, fill credentials, submit.
     * Does NOT assert anything — assertions belong in test classes.
     * Returns an InventoryPage instance; call ->waitUntilLoaded() on it
     * only after asserting a successful login.
     */
    public function loginAs(string $user, string $pass): InventoryPage
    {
        $this->open()
             ->enterUsername($user)
             ->enterPassword($pass)
             ->clickLogin();

        return new InventoryPage($this->driver);
    }

    public function getErrorMessage(): string
    {
        return $this->waitForVisible(Config::SEL_LOGIN_ERROR)->getText();
    }

    public function isErrorVisible(): bool
    {
        try {
            return $this->waitForVisible(Config::SEL_LOGIN_ERROR)->isDisplayed();
        } catch (\Exception) {
            return false;
        }
    }

    public function isOnLoginPage(): bool
    {
        // The login page is the root; no distinctive path segment to check,
        // so we verify the login button is present instead.
        try {
            return $this->waitForVisible(Config::SEL_LOGIN_BUTTON)->isDisplayed();
        } catch (\Exception) {
            return false;
        }
    }
}
