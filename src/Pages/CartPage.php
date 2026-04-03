<?php

declare(strict_types=1);

namespace SauceDemo\Pages;

use Facebook\WebDriver\WebDriverBy;
use SauceDemo\Config;

/**
 * Page Object for the SauceDemo shopping cart page (/cart.html).
 */
class CartPage extends BasePage
{
    public function waitUntilLoaded(): static
    {
        $this->waitForUrl(Config::PATH_CART);

        return $this;
    }

    public function getCartItemCount(): int
    {
        return count(
            $this->driver->findElements(WebDriverBy::cssSelector(Config::SEL_CART_ITEM))
        );
    }

    /** Returns the display names of all items currently in the cart. */
    public function getItemNames(): array
    {
        $elements = $this->driver->findElements(
            WebDriverBy::cssSelector(Config::SEL_ITEM_NAME)
        );

        return array_map(fn ($el) => $el->getText(), $elements);
    }

    /** Remove the first item listed in the cart. */
    public function removeFirstItem(): static
    {
        $buttons = $this->driver->findElements(
            WebDriverBy::cssSelector(Config::SEL_CART_REMOVE)
        );

        $buttons[0]->click();

        return $this;
    }

    public function clickCheckout(): CheckoutStepOnePage
    {
        $this->waitAndClick(Config::SEL_CHECKOUT_BTN);

        return new CheckoutStepOnePage($this->driver);
    }

    public function clickContinueShopping(): InventoryPage
    {
        $this->waitAndClick(Config::SEL_CONTINUE_SHOP);

        return new InventoryPage($this->driver);
    }

    public function isOnCartPage(): bool
    {
        return str_contains($this->getCurrentUrl(), Config::PATH_CART);
    }
}
