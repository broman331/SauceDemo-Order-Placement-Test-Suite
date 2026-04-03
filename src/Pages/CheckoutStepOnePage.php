<?php

declare(strict_types=1);

namespace SauceDemo\Pages;

use SauceDemo\Config;

/**
 * Page Object for checkout step 1 — personal information form
 * (/checkout-step-one.html).
 */
class CheckoutStepOnePage extends BasePage
{
    public function waitUntilLoaded(): static
    {
        $this->waitForUrl(Config::PATH_CHECKOUT_STEP1);
        $this->waitForVisible(Config::SEL_CONTINUE);

        return $this;
    }

    public function enterFirstName(string $value): static
    {
        $this->fillInput(Config::SEL_FIRST_NAME, $value);

        return $this;
    }

    public function enterLastName(string $value): static
    {
        $this->fillInput(Config::SEL_LAST_NAME, $value);

        return $this;
    }

    public function enterPostalCode(string $value): static
    {
        $this->fillInput(Config::SEL_POSTAL_CODE, $value);

        return $this;
    }

    /** Fill all three required fields in a single call. */
    public function fillForm(string $firstName, string $lastName, string $postalCode): static
    {
        return $this->enterFirstName($firstName)
                    ->enterLastName($lastName)
                    ->enterPostalCode($postalCode);
    }

    public function clickContinue(): CheckoutStepTwoPage
    {
        // Use JavaScript click: with multiple cart items React may still be
        // flushing state when the regular WebDriver click fires, causing the
        // button to be intercepted or the form validation to miss the values.
        $el = $this->waitForVisible(Config::SEL_CONTINUE);
        $this->driver->executeScript('arguments[0].click();', [$el]);

        return new CheckoutStepTwoPage($this->driver);
    }

    public function clickCancel(): CartPage
    {
        $this->waitAndClick(Config::SEL_CANCEL);

        return new CartPage($this->driver);
    }

    public function getErrorMessage(): string
    {
        return $this->waitForVisible(Config::SEL_FORM_ERROR)->getText();
    }

    public function isErrorVisible(): bool
    {
        try {
            return $this->waitForVisible(Config::SEL_FORM_ERROR)->isDisplayed();
        } catch (\Exception) {
            return false;
        }
    }

    public function isOnStep1Page(): bool
    {
        return str_contains($this->getCurrentUrl(), Config::PATH_CHECKOUT_STEP1);
    }
}
