<?php

declare(strict_types=1);

namespace SauceDemo;

/**
 * Single source of truth for all configuration, selectors, URLs, and test data.
 *
 * Selectors are CSS unless noted. All string constants are intentionally
 * public so test classes can use them in assertion messages when needed.
 */
final class Config
{
    // ── Runtime environment ───────────────────────────────────────────────────

    public static function seleniumHost(): string
    {
        return $_ENV['SELENIUM_HOST'] ?? 'http://localhost:4444';
    }

    public static function baseUrl(): string
    {
        return rtrim($_ENV['BASE_URL'] ?? 'https://www.saucedemo.com', '/');
    }

    public static function browser(): string
    {
        return strtolower($_ENV['BROWSER'] ?? 'chrome');
    }

    public static function headless(): bool
    {
        return filter_var($_ENV['HEADLESS'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
    }

    public static function explicitWait(): int
    {
        return (int) ($_ENV['EXPLICIT_WAIT'] ?? 10);
    }

    // ── Credentials ───────────────────────────────────────────────────────────

    const STANDARD_USER = 'standard_user';
    const LOCKED_USER   = 'locked_out_user';
    const PASSWORD      = 'secret_sauce';
    const INVALID_USER  = 'unknown_user';
    const INVALID_PASS  = 'wrong_pass';

    // ── URL paths (relative to BASE_URL) ─────────────────────────────────────

    const PATH_INVENTORY      = '/inventory.html';
    const PATH_CART           = '/cart.html';
    const PATH_CHECKOUT_STEP1 = '/checkout-step-one.html';
    const PATH_CHECKOUT_STEP2 = '/checkout-step-two.html';
    const PATH_CHECKOUT_DONE  = '/checkout-complete.html';

    // ── Login page selectors ──────────────────────────────────────────────────

    const SEL_USERNAME     = '#user-name';
    const SEL_PASSWORD     = '#password';
    const SEL_LOGIN_BUTTON = '#login-button';
    const SEL_LOGIN_ERROR  = '[data-test="error"]';

    // ── Inventory page selectors ──────────────────────────────────────────────

    const SEL_INVENTORY_ITEM = '.inventory_item';
    const SEL_ADD_TO_CART    = '.btn_primary.btn_inventory';
    const SEL_CART_BADGE     = '.shopping_cart_badge';
    const SEL_CART_LINK      = '#shopping_cart_container';
    const SEL_BURGER_MENU    = '#react-burger-menu-btn';
    const SEL_SORT_DROPDOWN  = '.product_sort_container';
    const SEL_LOGOUT_LINK    = '#logout_sidebar_link';
    const SEL_ITEM_NAME      = '.inventory_item_name';

    // ── Cart page selectors ───────────────────────────────────────────────────

    const SEL_CART_ITEM     = '.cart_item';
    const SEL_CHECKOUT_BTN  = '#checkout';
    const SEL_CONTINUE_SHOP = '#continue-shopping';
    const SEL_CART_REMOVE   = '.btn_secondary.cart_button';

    // ── Checkout step 1 selectors ─────────────────────────────────────────────

    const SEL_FIRST_NAME  = '#first-name';
    const SEL_LAST_NAME   = '#last-name';
    const SEL_POSTAL_CODE = '#postal-code';
    const SEL_CONTINUE    = '#continue';
    const SEL_CANCEL      = '#cancel';
    const SEL_FORM_ERROR  = '[data-test="error"]';

    // ── Checkout step 2 selectors ─────────────────────────────────────────────

    const SEL_SUMMARY_INFO = '.summary_info';
    const SEL_SUBTOTAL     = '.summary_subtotal_label';
    const SEL_TAX          = '.summary_tax_label';
    const SEL_TOTAL        = '.summary_total_label';
    const SEL_FINISH       = '#finish';

    // ── Checkout complete selectors ───────────────────────────────────────────

    const SEL_COMPLETE_HEADER = '.complete-header';
    const SEL_BACK_HOME       = '#back-to-products';

    // ── Checkout test data ────────────────────────────────────────────────────

    const CHECKOUT_FIRST = 'Jane';
    const CHECKOUT_LAST  = 'Doe';
    const CHECKOUT_ZIP   = '90210';
    const CONFIRM_TEXT   = 'Thank you for your order!';

    // ── Checkout step 1 validation error messages ─────────────────────────────

    const ERR_CHECKOUT_FIRST = 'First Name is required';
    const ERR_CHECKOUT_LAST  = 'Last Name is required';
    const ERR_CHECKOUT_ZIP   = 'Postal Code is required';
}
