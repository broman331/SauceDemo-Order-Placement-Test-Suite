<?php

declare(strict_types=1);

namespace SauceDemo\Pages;

use Facebook\WebDriver\WebDriverBy;
use SauceDemo\Config;

/**
 * Page Object for checkout step 2 — order review page
 * (/checkout-step-two.html).
 */
class CheckoutStepTwoPage extends BasePage
{
    public function waitUntilLoaded(): static
    {
        $this->waitForUrl(Config::PATH_CHECKOUT_STEP2);
        $this->waitForVisible(Config::SEL_SUMMARY_INFO);

        return $this;
    }

    public function getSubtotalText(): string
    {
        return $this->waitForVisible(Config::SEL_SUBTOTAL)->getText();
    }

    public function getTaxText(): string
    {
        return $this->waitForVisible(Config::SEL_TAX)->getText();
    }

    public function getTotalText(): string
    {
        return $this->waitForVisible(Config::SEL_TOTAL)->getText();
    }

    public function getLineItemNames(): array
    {
        $elements = $this->driver->findElements(
            WebDriverBy::cssSelector(Config::SEL_ITEM_NAME)
        );

        return array_map(fn ($el) => $el->getText(), $elements);
    }

    public function getLineItemCount(): int
    {
        return count(
            $this->driver->findElements(WebDriverBy::cssSelector(Config::SEL_CART_ITEM))
        );
    }

    public function clickFinish(): CheckoutCompletePage
    {
        // Use JavaScript click for the same reason as the logout button:
        // any lingering overlay from a previous render can intercept a regular
        // WebDriver click, leaving the page stuck on the review screen.
        $el = $this->waitForVisible(Config::SEL_FINISH);
        $this->driver->executeScript('arguments[0].click();', [$el]);

        return new CheckoutCompletePage($this->driver);
    }

    /**
     * Cancel on step 2 navigates back to the inventory page (SauceDemo behaviour).
     */
    public function clickCancel(): InventoryPage
    {
        $this->waitAndClick(Config::SEL_CANCEL);

        return new InventoryPage($this->driver);
    }

    public function isOnStep2Page(): bool
    {
        return str_contains($this->getCurrentUrl(), Config::PATH_CHECKOUT_STEP2);
    }
}
