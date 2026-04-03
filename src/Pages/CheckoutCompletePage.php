<?php

declare(strict_types=1);

namespace SauceDemo\Pages;

use SauceDemo\Config;

/**
 * Page Object for the order confirmation page (/checkout-complete.html).
 */
class CheckoutCompletePage extends BasePage
{
    public function waitUntilLoaded(): static
    {
        $this->waitForUrl(Config::PATH_CHECKOUT_DONE);
        $this->waitForVisible(Config::SEL_COMPLETE_HEADER);

        return $this;
    }

    public function getConfirmationText(): string
    {
        return $this->waitForVisible(Config::SEL_COMPLETE_HEADER)->getText();
    }

    public function clickBackToProducts(): InventoryPage
    {
        // Use JavaScript click for the same reason as the #finish and logout
        // buttons: any lingering overlay can intercept a regular WebDriver click.
        $el = $this->waitForVisible(Config::SEL_BACK_HOME);
        $this->driver->executeScript('arguments[0].click();', [$el]);

        return new InventoryPage($this->driver);
    }

    public function isOnCompletePage(): bool
    {
        return str_contains($this->getCurrentUrl(), Config::PATH_CHECKOUT_DONE);
    }
}
