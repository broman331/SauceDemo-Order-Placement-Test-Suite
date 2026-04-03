/**
 * SauceDemo — Order Placement Performance Suite
 *
 * Uses k6's built-in browser module (Chromium via Chrome DevTools Protocol).
 * No Selenium server required — k6 launches Chromium itself.
 *
 * Select a scenario via the SCENARIO environment variable (default: smoke):
 *   smoke  — 1 VU, 1 iteration:  validate the script runs end-to-end
 *   load   — 3 VUs, 1 minute:    stable baseline with concurrent users
 *   stress — ramp 1 → 8 → 0:    find the point at which timings degrade
 *
 * Run:
 *   SCENARIO=smoke  k6 run performance/checkout-flow.js
 *   SCENARIO=load   k6 run performance/checkout-flow.js
 *   SCENARIO=stress k6 run performance/checkout-flow.js
 *
 * Debug (headed browser):
 *   SCENARIO=smoke K6_BROWSER_HEADLESS=false k6 run performance/checkout-flow.js
 *
 * JSON output:
 *   SCENARIO=load k6 run --out json=build/reports/k6-results.json performance/checkout-flow.js
 */

import { browser } from 'k6/browser';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

// ── Selectors (mirrors src/Config.php) ──────────────────────────────────────

const SEL = {
    // Login page
    USERNAME:      '#user-name',
    PASSWORD:      '#password',
    LOGIN_BUTTON:  '#login-button',
    LOGIN_ERROR:   '[data-test="error"]',

    // Inventory page
    ADD_TO_CART:   '.btn_primary.btn_inventory',
    CART_BADGE:    '.shopping_cart_badge',
    CART_LINK:     '#shopping_cart_container',

    // Cart page
    CART_ITEM:     '.cart_item',
    CHECKOUT_BTN:  '#checkout',

    // Checkout step 1
    FIRST_NAME:    '#first-name',
    LAST_NAME:     '#last-name',
    POSTAL_CODE:   '#postal-code',
    CONTINUE:      '#continue',

    // Checkout step 2
    SUMMARY_INFO:  '.summary_info',
    FINISH:        '#finish',

    // Checkout complete
    COMPLETE_HDR:  '.complete-header',
};

// ── Credentials / config (overridable via env vars) ─────────────────────────

const BASE_URL  = __ENV.BASE_URL       || 'https://www.saucedemo.com';
const USERNAME  = __ENV.TEST_USER      || 'standard_user';
const PASSWORD  = __ENV.TEST_PASS      || 'secret_sauce';
const SCENARIO  = __ENV.SCENARIO       || 'smoke';

const CHECKOUT  = {
    firstName:  __ENV.CHECKOUT_FIRST || 'Jane',
    lastName:   __ENV.CHECKOUT_LAST  || 'Doe',
    postalCode: __ENV.CHECKOUT_ZIP   || '90210',
};

// ── Scenario definitions ─────────────────────────────────────────────────────
//
// All three definitions are kept here for reference. The SCENARIO env var
// selects which one is exported in options — only the selected scenario runs.

const SCENARIOS = {
    /**
     * smoke — 1 VU, 1 iteration.
     * Purpose: verify the script runs end-to-end without errors before
     * committing to a longer load or stress run.
     */
    smoke: {
        executor:    'per-vu-iterations',
        vus:         1,
        iterations:  1,
        maxDuration: '2m',
        options:     { browser: { type: 'chromium' } },
    },

    /**
     * load — 3 concurrent VUs for 1 minute.
     * Purpose: establish a stable performance baseline and capture
     * realistic p50/p95 percentiles under light concurrent load.
     * VU count is intentionally conservative (each VU = a real browser).
     */
    load: {
        executor: 'constant-vus',
        vus:      3,
        duration: '1m',
        options:  { browser: { type: 'chromium' } },
    },

    /**
     * stress — ramp from 1 up to 6 VUs then back to 0.
     * Purpose: identify the concurrency level at which step timings
     * begin to degrade beyond the defined thresholds.
     *
     * Peak is capped at 6 (not 8) — each VU is a real Chromium instance
     * and 8+ concurrent browsers triggers the macOS OOM killer on a laptop.
     * startVUs: 1 ensures the first VU is active immediately rather than
     * ramping from 0 during the warm-up stage.
     */
    stress: {
        executor: 'ramping-vus',
        startVUs: 1,
        stages: [
            { duration: '30s', target: 1 },  // warm up at 1 VU
            { duration: '30s', target: 3 },  // ramp up
            { duration: '30s', target: 6 },  // peak load
            { duration: '30s', target: 0 },  // ramp down
        ],
        options: { browser: { type: 'chromium' } },
    },
};

if (!SCENARIOS[SCENARIO]) {
    throw new Error(`Unknown SCENARIO "${SCENARIO}". Valid values: smoke, load, stress`);
}

// ── Custom timing metrics ────────────────────────────────────────────────────

const e2eDuration    = new Trend('checkout_e2e_duration',    true);
const loginDuration  = new Trend('checkout_login_duration',  true);
const cartDuration   = new Trend('checkout_cart_duration',   true);
const step1Duration  = new Trend('checkout_step1_duration',  true);
const step2Duration  = new Trend('checkout_step2_duration',  true);

// ── k6 options: active scenario + thresholds ────────────────────────────────

export const options = {
    scenarios: {
        [SCENARIO]: SCENARIOS[SCENARIO],
    },

    thresholds: {
        // Full flow must complete in under 20 s at p95
        'checkout_e2e_duration':   ['p(95) < 20000'],
        // Form fill + Continue must resolve in under 8 s at p95
        'checkout_step1_duration': ['p(95) < 8000'],
        // Finish → confirmation must resolve in under 5 s at p95
        'checkout_step2_duration': ['p(95) < 5000'],
        // Core Web Vitals — Largest Contentful Paint (p75 < 2.5 s)
        'browser_web_vital_lcp':   ['p(75) < 2500'],
        // Core Web Vitals — First Contentful Paint (p75 < 1.8 s)
        'browser_web_vital_fcp':   ['p(75) < 1800'],
        // At least 95 % of all check() assertions must pass
        'checks':                  ['rate > 0.95'],
    },
};

// ── Helper: fill a React-controlled input ───────────────────────────────────
//
// SauceDemo's form fields are React controlled inputs. Standard
// locator.fill() / type() only set the DOM value without triggering React's
// synthetic event system, so the component never updates its internal state
// and the Continue button sees empty fields.
//
// This replicates the native HTMLInputElement value setter trick used in
// BasePage::fillInput() (PHP / Selenium side), dispatching real input + change
// events so React's onChange handler fires and commits the value.

async function fillReactInput(page, selector, value) {
    await page.evaluate(
        ([sel, val]) => {
            const el     = document.querySelector(sel);
            const setter = Object.getOwnPropertyDescriptor(
                window.HTMLInputElement.prototype,
                'value'
            ).set;
            setter.call(el, val);
            el.dispatchEvent(new Event('input',  { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        },
        [selector, value]
    );

    // Poll until the DOM attribute matches — confirms React flushed the update
    await page.locator(selector).waitFor({ state: 'visible' });
}

// ── Helper: JavaScript click (bypasses overlay intercepts) ──────────────────
//
// Several SauceDemo buttons (#finish, #continue, #back-to-products) sit behind
// a translucent overlay rendered by the react-burger-menu library. A regular
// locator.click() lands on the overlay rather than the button. Using
// element.click() via evaluate() bypasses the overlay entirely — the same fix
// applied to the Selenium suite.

async function jsClick(page, selector) {
    await page.evaluate(
        (sel) => document.querySelector(sel).click(),
        selector
    );
}

// ── Default export: full order-placement checkout flow ──────────────────────
//
// Called once per VU iteration across all three scenarios.
// Mirrors TC007 (testFullOrderPlacementHappyPath) step for step.

export default async function checkoutFlow() {
    const page      = await browser.newPage();
    const flowStart = Date.now();

    try {
        // ── Login ──────────────────────────────────────────────────────────
        // The login fields are standard HTML inputs — locator.fill() is
        // sufficient; the React native-setter trick is only needed for the
        // controlled inputs on the checkout step-1 form.
        const tLogin = Date.now();

        await page.goto(BASE_URL);
        await page.locator(SEL.LOGIN_BUTTON).waitFor({ state: 'visible' });

        await page.locator(SEL.USERNAME).fill(USERNAME);
        await page.locator(SEL.PASSWORD).fill(PASSWORD);
        await page.locator(SEL.LOGIN_BUTTON).click();

        // Wait for a landmark element that only exists on the inventory page
        // instead of waitForURL — more reliable across k6 versions.
        await page.locator(SEL.ADD_TO_CART).first().waitFor({ state: 'visible' });

        loginDuration.add(Date.now() - tLogin);

        check(page, {
            'login: landed on inventory': (p) => p.url().includes('/inventory.html'),
        });

        // ── Add to cart ────────────────────────────────────────────────────
        const tCart = Date.now();

        await page.locator(SEL.ADD_TO_CART).first().click();
        await page.locator(SEL.CART_LINK).click();

        // Wait for a cart item to confirm we are on the cart page
        await page.locator(SEL.CART_ITEM).first().waitFor({ state: 'visible' });

        cartDuration.add(Date.now() - tCart);

        // locator.count() and locator.textContent() are async in k6 browser —
        // they must be awaited before the synchronous check() call.
        const cartItemCount = await page.locator(SEL.CART_ITEM).count();
        const cartBadgeText = await page.locator(SEL.CART_BADGE).textContent();

        check(page, {
            'cart: at least one item present': () => cartItemCount > 0,
            'cart: badge shows 1':             () => cartBadgeText === '1',
        });

        // ── Checkout step 1 (personal information) ─────────────────────────
        // These ARE React controlled inputs — fillReactInput() is required.
        const tStep1 = Date.now();

        await page.locator(SEL.CHECKOUT_BTN).click();
        await page.locator(SEL.FIRST_NAME).waitFor({ state: 'visible' });

        await fillReactInput(page, SEL.FIRST_NAME,  CHECKOUT.firstName);
        await fillReactInput(page, SEL.LAST_NAME,   CHECKOUT.lastName);
        await fillReactInput(page, SEL.POSTAL_CODE, CHECKOUT.postalCode);

        // JS click bypasses the overlay on the Continue button
        await jsClick(page, SEL.CONTINUE);

        // Wait for the order summary — only present on step 2
        await page.locator(SEL.SUMMARY_INFO).waitFor({ state: 'visible' });

        step1Duration.add(Date.now() - tStep1);

        check(page, {
            'step1: advanced to step 2': (p) => p.url().includes('/checkout-step-two.html'),
        });

        // ── Checkout step 2 (review + finish) ─────────────────────────────
        const tStep2 = Date.now();

        // JS click bypasses overlay on the Finish button
        await jsClick(page, SEL.FINISH);

        // Wait for the confirmation header — only present on the complete page
        await page.locator(SEL.COMPLETE_HDR).waitFor({ state: 'visible' });

        step2Duration.add(Date.now() - tStep2);

        const confirmText = await page.locator(SEL.COMPLETE_HDR).textContent();

        check(page, {
            'step2: reached confirmation page':    (p) => p.url().includes('/checkout-complete.html'),
            'step2: confirmation message visible': ()  => confirmText.includes('Thank you'),
        });

        // Record total end-to-end duration
        e2eDuration.add(Date.now() - flowStart);

    } finally {
        // Always close the page — prevents leaked browser contexts between
        // iterations, especially important during the stress ramp-down.
        await page.close();
    }

    // Brief think-time between iterations (realistic user pacing)
    sleep(1);
}
