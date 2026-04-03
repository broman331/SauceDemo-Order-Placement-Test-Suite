<?php

declare(strict_types=1);

namespace SauceDemo\Tests;

/**
 * Regression tests for cart management.
 *
 * TC004 — Add a single product to the cart
 * TC005 — Add multiple products to the cart
 * TC006 — Remove an item from the cart page
 */
class CartTest extends BaseTest
{
    /**
     * TC004
     * Given an authenticated user on the inventory page with an empty cart
     * When they click "Add to cart" for one product
     * Then the cart badge increments to 1
     */
    public function testAddSingleProductToCart(): void
    {
        $inventory = $this->loginAsStandardUser();

        // Fresh session always starts with an empty cart — no need to pre-check
        // the badge (which would cause a 10-second wait for an element that
        // doesn't exist yet, interfering with the subsequent badge detection).
        $inventory->addNthItemToCart(0);

        $this->assertSame(
            1,
            $inventory->getCartBadgeCount(),
            'Cart badge should show 1 after adding one item'
        );
    }

    /**
     * TC005
     * Given an authenticated user on the inventory page with an empty cart
     * When they add three different products
     * Then the cart badge shows 3
     */
    public function testAddMultipleProductsToCart(): void
    {
        $inventory = $this->loginAsStandardUser();

        $inventory
            ->addNthItemToCart(0)
            ->addNthItemToCart(1)
            ->addNthItemToCart(2);

        $this->assertSame(
            3,
            $inventory->getCartBadgeCount(),
            'Cart badge should show 3 after adding three products'
        );
    }

    /**
     * TC006
     * Given an authenticated user with two items in the cart
     * When they navigate to the cart page and remove one item
     * Then the cart contains one item fewer
     */
    public function testRemoveItemFromCart(): void
    {
        $inventory = $this->loginAsStandardUser();
        $inventory->addNthItemToCart(0)->addNthItemToCart(1);

        $cart = $inventory->goToCart()->waitUntilLoaded();

        $countBefore = $cart->getCartItemCount(); // 2

        $cart->removeFirstItem();

        $this->assertSame(
            $countBefore - 1,
            $cart->getCartItemCount(),
            'Cart should contain one fewer item after removing one'
        );
    }

    /**
     * TC017
     * Given an authenticated user who adds a specific product to the cart
     * When they navigate to the cart page
     * Then the cart displays that product's name
     */
    public function testCartShowsCorrectProductName(): void
    {
        $inventory = $this->loginAsStandardUser();

        // Capture the name before adding so we can assert it appears in the cart
        $productNames = $inventory->getProductNames();
        $addedProduct = $productNames[0];

        $inventory->addNthItemToCart(0);

        $cart = $inventory->goToCart()->waitUntilLoaded();

        $this->assertContains(
            $addedProduct,
            $cart->getItemNames(),
            'The cart should display the name of the product that was added from the inventory'
        );
    }

    /**
     * TC016
     * Given an authenticated user who has added items to the cart
     * When they click "Continue Shopping" from the cart page
     * Then they return to the inventory page with the cart contents preserved
     */
    public function testContinueShoppingPreservesCart(): void
    {
        $inventory = $this->loginAsStandardUser();
        $inventory->addNthItemToCart(0)->addNthItemToCart(1);

        $cart = $inventory->goToCart()->waitUntilLoaded();
        $this->assertSame(2, $cart->getCartItemCount(),
            'Cart should contain 2 items before continuing shopping');

        $backToInventory = $cart->clickContinueShopping()->waitUntilLoaded();

        $this->assertTrue($backToInventory->isOnInventoryPage(),
            'Continue Shopping should return the user to the inventory page');
        $this->assertSame(2, $backToInventory->getCartBadgeCount(),
            'Cart badge should still show 2 after returning to inventory');
    }
}
