<?php

declare(strict_types=1);

namespace SauceDemo\Tests;

use SauceDemo\Config;

/**
 * Regression tests for the order placement / checkout process.
 *
 * TC007 — Full order placement happy path
 * TC008 — Checkout fails when required fields are empty
 * TC009 — Cancel on step 1 returns to the cart
 * TC010 — Cancel on step 2 stays on the review page
 * TC011 — Order summary shows the correct item and price labels
 * TC012 — Logout via the hamburger menu
 */
class CheckoutTest extends BaseTest
{
    /**
     * TC007
     * Given a logged-in user who adds one item to the cart
     * When they complete all checkout steps with valid data
     * Then the confirmation page displays a success message
     */
    public function testFullOrderPlacementHappyPath(): void
    {
        // 1. Login
        $inventory = $this->loginAsStandardUser();

        // 2. Add one product to the cart
        $inventory->addNthItemToCart(0);
        $this->assertSame(1, $inventory->getCartBadgeCount(),
            'Cart badge should show 1 after adding one item');

        // 3. Navigate to the cart
        $cart = $inventory->goToCart()->waitUntilLoaded();
        $this->assertSame(1, $cart->getCartItemCount(),
            'Cart should contain 1 item');

        // 4. Begin checkout
        $step1 = $cart->clickCheckout()->waitUntilLoaded();
        $this->assertTrue($step1->isOnStep1Page(),
            'Should be on checkout step 1');

        // 5. Fill personal information and proceed
        $step2 = $step1
            ->fillForm(Config::CHECKOUT_FIRST, Config::CHECKOUT_LAST, Config::CHECKOUT_ZIP)
            ->clickContinue()
            ->waitUntilLoaded();
        $this->assertTrue($step2->isOnStep2Page(),
            'Should be on the order review page');

        // 6. Verify item count on review page and finish
        $this->assertSame(1, $step2->getLineItemCount(),
            'Review page should list 1 item');

        $complete = $step2->clickFinish()->waitUntilLoaded();

        // 7. Assert confirmation
        $this->assertTrue($complete->isOnCompletePage(),
            'Should land on the checkout complete page');
        $this->assertStringContainsString(
            Config::CONFIRM_TEXT,
            $complete->getConfirmationText(),
            'Confirmation message should say "Thank you for your order!"'
        );
    }

    /**
     * TC008
     * Given a logged-in user who has reached checkout step 1
     * When they submit the form without filling any fields
     * Then a validation error is displayed and the user stays on step 1
     */
    public function testCheckoutFailsWithEmptyFields(): void
    {
        $inventory = $this->loginAsStandardUser();
        $inventory->addNthItemToCart(0);

        $step1 = $inventory->goToCart()->waitUntilLoaded()
                           ->clickCheckout()->waitUntilLoaded();

        // Submit without entering any data
        $step1->clickContinue();

        $this->assertTrue($step1->isErrorVisible(),
            'A validation error should appear when required fields are left empty');
        $this->assertTrue($step1->isOnStep1Page(),
            'User should remain on step 1 after failing validation');
    }

    /**
     * TC009
     * Given a logged-in user who has reached checkout step 1
     * When they click Cancel
     * Then they are returned to the cart page
     */
    public function testCheckoutCancelFromStep1ReturnsToCart(): void
    {
        $inventory = $this->loginAsStandardUser();
        $inventory->addNthItemToCart(0);

        $step1 = $inventory->goToCart()->waitUntilLoaded()
                           ->clickCheckout()->waitUntilLoaded();

        $cart = $step1->clickCancel()->waitUntilLoaded();

        $this->assertTrue($cart->isOnCartPage(),
            'Cancelling from step 1 should return the user to the cart');
    }

    /**
     * TC010
     * Given a logged-in user on the order review page (step 2)
     * When they click Cancel
     * Then they are returned to the inventory page (SauceDemo behaviour)
     */
    public function testCheckoutCancelFromStep2ReturnsToInventory(): void
    {
        $inventory = $this->loginAsStandardUser();
        $inventory->addNthItemToCart(0);

        $step2 = $inventory->goToCart()->waitUntilLoaded()
                           ->clickCheckout()->waitUntilLoaded()
                           ->fillForm(Config::CHECKOUT_FIRST, Config::CHECKOUT_LAST, Config::CHECKOUT_ZIP)
                           ->clickContinue()->waitUntilLoaded();

        $backToInventory = $step2->clickCancel()->waitUntilLoaded();

        $this->assertTrue($backToInventory->isOnInventoryPage(),
            'Cancelling from step 2 should return the user to the inventory page');
    }

    /**
     * TC011
     * Given a logged-in user who added a specific product and reached step 2
     * When they view the order summary
     * Then the correct product name and currency-formatted price labels are present
     */
    public function testOrderSummaryShowsCorrectItemsAndPrice(): void
    {
        $inventory = $this->loginAsStandardUser();

        // Capture the name of the first listed product before adding it
        $productNames = $inventory->getProductNames();
        $addedProduct = $productNames[0];

        $inventory->addNthItemToCart(0);

        $step2 = $inventory->goToCart()->waitUntilLoaded()
                           ->clickCheckout()->waitUntilLoaded()
                           ->fillForm(Config::CHECKOUT_FIRST, Config::CHECKOUT_LAST, Config::CHECKOUT_ZIP)
                           ->clickContinue()->waitUntilLoaded();

        $this->assertContains(
            $addedProduct,
            $step2->getLineItemNames(),
            'The order summary should list the product that was added to the cart'
        );

        $this->assertStringContainsString('$', $step2->getSubtotalText(),
            'Subtotal label should contain a currency value');
        $this->assertStringContainsString('$', $step2->getTaxText(),
            'Tax label should contain a currency value');
        $this->assertStringContainsString('$', $step2->getTotalText(),
            'Total label should contain a currency value');
    }

    /**
     * TC012
     * Given a logged-in user on the inventory page
     * When they open the hamburger menu and click Logout
     * Then they are redirected to the login page
     * And attempting to access /inventory.html redirects back to login
     */
    public function testLogoutViaMenuReturnsToLoginPage(): void
    {
        $inventory = $this->loginAsStandardUser();

        $loginPage = $inventory->openBurgerMenu()->clickLogout();

        $this->assertTrue($loginPage->isOnLoginPage(),
            'Logging out should redirect the user to the login page');

        // Attempt to access a protected page after logout
        $this->driver->get(Config::baseUrl() . Config::PATH_INVENTORY);

        $this->assertTrue($loginPage->isOnLoginPage(),
            'Accessing /inventory.html after logout should redirect back to the login page');
    }

    /**
     * TC013
     * Given a logged-in user who adds three different products to the cart
     * When they complete the full checkout flow
     * Then the order summary lists all three items and the confirmation page is shown
     */
    public function testMultipleItemsOrderPlacement(): void
    {
        $inventory = $this->loginAsStandardUser();

        // Badge count with 3 items is already covered by TC005 — no assertion here
        // to avoid a timing delay after 3 rapid cart additions that could
        // interfere with the subsequent form fill on checkout step 1.
        $inventory->addNthItemToCart(0)->addNthItemToCart(1)->addNthItemToCart(2);

        $step2 = $inventory->goToCart()->waitUntilLoaded()
                           ->clickCheckout()->waitUntilLoaded()
                           ->fillForm(Config::CHECKOUT_FIRST, Config::CHECKOUT_LAST, Config::CHECKOUT_ZIP)
                           ->clickContinue()->waitUntilLoaded();

        $this->assertSame(3, $step2->getLineItemCount(),
            'Order summary should list all 3 items');

        $complete = $step2->clickFinish()->waitUntilLoaded();

        $this->assertTrue($complete->isOnCompletePage(),
            'Should land on the checkout complete page');
        $this->assertStringContainsString(
            Config::CONFIRM_TEXT,
            $complete->getConfirmationText(),
            'Confirmation message should say "Thank you for your order!"'
        );
    }

    /**
     * TC014a
     * Given a logged-in user on checkout step 1
     * When they submit the form with first name left empty
     * Then a validation error is shown and the user stays on step 1
     */
    public function testCheckoutFailsWithMissingFirstName(): void
    {
        $inventory = $this->loginAsStandardUser();
        $inventory->addNthItemToCart(0);

        $step1 = $inventory->goToCart()->waitUntilLoaded()
                           ->clickCheckout()->waitUntilLoaded();

        $step1->enterLastName(Config::CHECKOUT_LAST)
              ->enterPostalCode(Config::CHECKOUT_ZIP)
              ->clickContinue();

        $this->assertTrue($step1->isErrorVisible(),
            'A validation error should appear when first name is missing');
        $this->assertStringContainsString(
            Config::ERR_CHECKOUT_FIRST,
            $step1->getErrorMessage(),
            'Validation error should name the missing field (First Name)'
        );
        $this->assertTrue($step1->isOnStep1Page(),
            'User should remain on step 1 when first name is missing');
    }

    /**
     * TC014b
     * Given a logged-in user on checkout step 1
     * When they submit the form with last name left empty
     * Then a validation error is shown and the user stays on step 1
     */
    public function testCheckoutFailsWithMissingLastName(): void
    {
        $inventory = $this->loginAsStandardUser();
        $inventory->addNthItemToCart(0);

        $step1 = $inventory->goToCart()->waitUntilLoaded()
                           ->clickCheckout()->waitUntilLoaded();

        $step1->enterFirstName(Config::CHECKOUT_FIRST)
              ->enterPostalCode(Config::CHECKOUT_ZIP)
              ->clickContinue();

        $this->assertTrue($step1->isErrorVisible(),
            'A validation error should appear when last name is missing');
        $this->assertStringContainsString(
            Config::ERR_CHECKOUT_LAST,
            $step1->getErrorMessage(),
            'Validation error should name the missing field (Last Name)'
        );
        $this->assertTrue($step1->isOnStep1Page(),
            'User should remain on step 1 when last name is missing');
    }

    /**
     * TC014c
     * Given a logged-in user on checkout step 1
     * When they submit the form with postal code left empty
     * Then a validation error is shown and the user stays on step 1
     */
    public function testCheckoutFailsWithMissingPostalCode(): void
    {
        $inventory = $this->loginAsStandardUser();
        $inventory->addNthItemToCart(0);

        $step1 = $inventory->goToCart()->waitUntilLoaded()
                           ->clickCheckout()->waitUntilLoaded();

        $step1->enterFirstName(Config::CHECKOUT_FIRST)
              ->enterLastName(Config::CHECKOUT_LAST)
              ->clickContinue();

        $this->assertTrue($step1->isErrorVisible(),
            'A validation error should appear when postal code is missing');
        $this->assertStringContainsString(
            Config::ERR_CHECKOUT_ZIP,
            $step1->getErrorMessage(),
            'Validation error should name the missing field (Postal Code)'
        );
        $this->assertTrue($step1->isOnStep1Page(),
            'User should remain on step 1 when postal code is missing');
    }

    /**
     * TC015
     * Given a logged-in user who has just completed an order
     * When they click "Back to Products" on the confirmation page
     * Then they land on the inventory page with an empty cart
     */
    public function testBackToProductsAfterOrderCompletion(): void
    {
        $inventory = $this->loginAsStandardUser();
        $inventory->addNthItemToCart(0);

        $complete = $inventory->goToCart()->waitUntilLoaded()
                              ->clickCheckout()->waitUntilLoaded()
                              ->fillForm(Config::CHECKOUT_FIRST, Config::CHECKOUT_LAST, Config::CHECKOUT_ZIP)
                              ->clickContinue()->waitUntilLoaded()
                              ->clickFinish()->waitUntilLoaded();

        $this->assertTrue($complete->isOnCompletePage(),
            'Should be on the confirmation page before clicking Back to Products');

        $backToInventory = $complete->clickBackToProducts()->waitUntilLoaded();

        $this->assertTrue($backToInventory->isOnInventoryPage(),
            'Back to Products should return the user to the inventory page');
        $this->assertSame(0, $backToInventory->getCartBadgeCount(),
            'Cart should be empty after completing an order');
    }
}
