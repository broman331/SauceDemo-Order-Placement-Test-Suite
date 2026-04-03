<?php

declare(strict_types=1);

namespace SauceDemo\Pages;

use Facebook\WebDriver\WebDriverBy;
use SauceDemo\Config;

/**
 * Page Object for the SauceDemo inventory/products page (/inventory.html).
 */
class InventoryPage extends BasePage
{
    public function waitUntilLoaded(): static
    {
        $this->waitForUrl(Config::PATH_INVENTORY);
        $this->waitForVisible(Config::SEL_INVENTORY_ITEM);

        return $this;
    }

    /**
     * Click the "Add to cart" button for the Nth product (0-indexed).
     * Returns self so multiple calls can be chained.
     */
    public function addNthItemToCart(int $index = 0): static
    {
        $buttons = $this->driver->findElements(
            WebDriverBy::cssSelector(Config::SEL_ADD_TO_CART)
        );

        $buttons[$index]->click();

        return $this;
    }

    /**
     * Returns the integer shown in the cart badge, or 0 when the badge
     * is absent (empty cart).
     */
    public function getCartBadgeCount(): int
    {
        try {
            return (int) $this->waitForVisible(Config::SEL_CART_BADGE)->getText();
        } catch (\Exception) {
            return 0;
        }
    }

    /** Returns the display names of all products on the page. */
    public function getProductNames(): array
    {
        $elements = $this->driver->findElements(
            WebDriverBy::cssSelector(Config::SEL_ITEM_NAME)
        );

        return array_map(fn ($el) => $el->getText(), $elements);
    }

    public function goToCart(): CartPage
    {
        $this->waitAndClick(Config::SEL_CART_LINK);

        return new CartPage($this->driver);
    }

    public function openBurgerMenu(): static
    {
        $this->waitAndClick(Config::SEL_BURGER_MENU);

        // Wait for the sidebar slide-in animation to complete before returning.
        // Without this, clickLogout() fires mid-animation and the click misses.
        $this->waitForVisible(Config::SEL_LOGOUT_LINK);

        return $this;
    }

    public function clickLogout(): LoginPage
    {
        // The react-burger-menu sidebar creates a translucent overlay that can
        // sit above the menu items and intercept a regular WebDriver click.
        // JavaScript's element.click() fires directly on the target element,
        // bypassing any overlay with a higher z-index.
        $el = $this->waitForVisible(Config::SEL_LOGOUT_LINK);
        $this->driver->executeScript('arguments[0].click();', [$el]);

        return new LoginPage($this->driver);
    }

    public function isOnInventoryPage(): bool
    {
        return str_contains($this->getCurrentUrl(), Config::PATH_INVENTORY);
    }
}
