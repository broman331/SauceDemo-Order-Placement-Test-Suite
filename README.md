# SauceDemo — Order Placement Test Suite

Automated test suite for the order placement process on [SauceDemo](https://www.saucedemo.com/), covering two complementary layers:

| Layer | Tool | What it verifies |
|---|---|---|
| **Regression** | PHP · PHPUnit · Selenium | Functional correctness — every step of the checkout flow behaves as expected |
| **Performance** | k6 · Chromium (CDP) | Timing and stability — the flow completes within acceptable time under concurrent load |

---

## Project Structure

```
project-root/
├── README.md                        ← this file
├── composer.json                    ← PHP dependencies and composer scripts
├── phpunit.xml                      ← regression suites, env vars, JUnit reporting
├── build/
│   └── reports/
│       ├── junit.xml                ← PHPUnit JUnit XML report (generated)
│       └── k6-results.json         ← k6 JSON export (generated, optional)
├── performance/
│   └── checkout-flow.js            ← k6 browser performance suite (smoke / load / stress)
├── src/
│   ├── Config.php                   ← single source of truth: selectors, URLs, credentials
│   └── Pages/
│       ├── BasePage.php             ← shared explicit-wait helpers (all pages extend this)
│       ├── LoginPage.php            ← / (login form)
│       ├── InventoryPage.php        ← /inventory.html
│       ├── CartPage.php             ← /cart.html
│       ├── CheckoutStepOnePage.php  ← /checkout-step-one.html (personal info)
│       ├── CheckoutStepTwoPage.php  ← /checkout-step-two.html (order review)
│       └── CheckoutCompletePage.php ← /checkout-complete.html (confirmation)
└── tests/
    ├── BaseTest.php                 ← WebDriver setup/teardown; loginAsStandardUser() helper
    ├── LoginTest.php                ← TC001–TC003
    ├── CartTest.php                 ← TC004–TC006, TC016–TC017
    └── CheckoutTest.php             ← TC007–TC015
```

---

---

# Part 1 — Regression Tests

Functional correctness tests driven by a real browser via Selenium WebDriver. Each test opens a fresh browser session, exercises one specific behaviour of the order placement flow, and asserts the expected outcome.

## Tech Stack

| Component | Choice | Reason |
|---|---|---|
| Language | PHP 8.2+ | Project requirement |
| Test runner | PHPUnit 11 | Standard PHP testing framework; programmatic, not recording-based |
| Browser automation | php-webdriver/webdriver (Facebook) | Official PHP WebDriver bindings; code-driven, not a recorder |
| Pattern | Page Object Model (POM) | Separates selectors and interactions from test logic; easy to maintain |
| Browser | Chrome (default) / Firefox | Configurable via env var |

## Prerequisites

1. **PHP 8.2+** with the `curl` and `json` extensions enabled
2. **Composer** — [getcomposer.org](https://getcomposer.org)
3. **Selenium Server 4.x standalone** — [selenium.dev/downloads](https://www.selenium.dev/downloads/)
4. **ChromeDriver** matching your installed Chrome version — [chromedriver.chromium.org](https://chromedriver.chromium.org/downloads)
   *(or GeckoDriver for Firefox — [github.com/mozilla/geckodriver](https://github.com/mozilla/geckodriver/releases))*

Both Selenium Server and the browser driver must be running before executing tests.

## Installation

```bash
# 1. Install PHP dependencies
composer install

# 2. Start Selenium Server in a separate terminal (current version: 4.41.0)
java -jar selenium-server-<version>.jar standalone
```

Selenium listens on `http://localhost:4444` by default, matching the `SELENIUM_HOST` default.

## Configuration

All settings are controlled via environment variables defined in `phpunit.xml`. Override any of them at runtime:

| Variable | Default | Description |
|---|---|---|
| `SELENIUM_HOST` | `http://localhost:4444` | Selenium Server or Grid URL |
| `BASE_URL` | `https://www.saucedemo.com` | Target application URL |
| `BROWSER` | `chrome` | Browser to use: `chrome` or `firefox` |
| `HEADLESS` | `true` | Run browser headless: `true` or `false` |
| `IMPLICIT_WAIT` | `0` | Must stay `0` — all sync uses explicit waits |
| `EXPLICIT_WAIT` | `10` | Seconds to wait for elements before timeout |

Example — visible browser against a staging target:

```bash
HEADLESS=false BASE_URL=https://staging.saucedemo.com ./vendor/bin/phpunit --testsuite Regression
```

## Running the Regression Tests

```bash
# Full regression suite (all 19 tests)
./vendor/bin/phpunit --testsuite Regression

# By area
./vendor/bin/phpunit --testsuite Login     # TC001–TC003
./vendor/bin/phpunit --testsuite Cart      # TC004–TC006, TC016–TC017
./vendor/bin/phpunit --testsuite Checkout  # TC007–TC015

# Via composer scripts
composer test:regression
composer test:login
composer test:cart
composer test:checkout
```

A JUnit XML report is written to `build/reports/junit.xml` after each run.

## Test Scenarios

### Login (3 tests)

| ID | Method | What is tested |
|---|---|---|
| TC001 | `testSuccessfulLogin` | `standard_user` logs in → lands on inventory page |
| TC002 | `testLoginWithInvalidCredentials` | Invalid credentials → error message displayed, stays on login |
| TC003 | `testLockedOutUserCannotLogin` | `locked_out_user` → lockout error, stays on login |

### Cart (5 tests)

| ID | Method | What is tested |
|---|---|---|
| TC004 | `testAddSingleProductToCart` | Add 1 item → cart badge = 1 |
| TC005 | `testAddMultipleProductsToCart` | Add 3 items → cart badge = 3 |
| TC006 | `testRemoveItemFromCart` | Remove item from cart → count decreases by 1 |
| TC016 | `testContinueShoppingPreservesCart` | Continue Shopping → returns to inventory with cart intact |
| TC017 | `testCartShowsCorrectProductName` | Product added from inventory appears in cart by name |

### Checkout (11 tests)

| ID | Method | What is tested |
|---|---|---|
| TC007 | `testFullOrderPlacementHappyPath` | Full flow: login → add item → cart → personal info → review → confirmation |
| TC008 | `testCheckoutFailsWithEmptyFields` | Submit step 1 with all fields empty → validation error, stays on step 1 |
| TC009 | `testCheckoutCancelFromStep1ReturnsToCart` | Cancel on step 1 → returns to cart |
| TC010 | `testCheckoutCancelFromStep2ReturnsToInventory` | Cancel on step 2 → returns to inventory page |
| TC011 | `testOrderSummaryShowsCorrectItemsAndPrice` | Review page lists correct product name and $ price labels |
| TC012 | `testLogoutViaMenuReturnsToLoginPage` | Logout via hamburger menu → login page; protected route also redirects |
| TC013 | `testMultipleItemsOrderPlacement` | Add 3 items → complete checkout → all 3 appear in order summary |
| TC014a | `testCheckoutFailsWithMissingFirstName` | Submit with first name empty → field-specific validation error |
| TC014b | `testCheckoutFailsWithMissingLastName` | Submit with last name empty → field-specific validation error |
| TC014c | `testCheckoutFailsWithMissingPostalCode` | Submit with postal code empty → field-specific validation error |
| TC015 | `testBackToProductsAfterOrderCompletion` | After confirmation → Back to Products → inventory page, cart is empty |

## Design Decisions

**Per-test browser sessions**
`setUp` and `tearDown` run at method scope — every test gets a fresh `RemoteWebDriver`. Slower than sharing a session per class, but eliminates all risk of cart state, cookies, or login state leaking between tests.

**Explicit waits only, zero implicit wait**
`IMPLICIT_WAIT=0` in `phpunit.xml` and `implicitlyWait(0)` in `BaseTest::setUp()`. Mixing implicit and explicit waits causes non-deterministic timeouts that are hard to diagnose.

**Return types communicate page transitions**
Navigation methods return the page object for the destination (e.g. `CartPage::clickCheckout(): CheckoutStepOnePage`). The flow is self-documenting, the wrong page's methods cannot be called, and no `sleep()` is needed — `waitUntilLoaded()` on the returned object handles synchronisation.

**No assertions in page objects**
Page objects expose state-query methods (`isOnCartPage()`, `getErrorMessage()`). All `$this->assert*` calls live exclusively in test classes, keeping PHPUnit out of the page layer.

**`Config.php` as the single source of truth**
Every CSS selector, URL path, credential, and timeout is defined exactly once. A selector change in the app requires editing one line in `Config.php`. The `BASE_URL` env-var override makes it trivial to point the suite at a staging environment.

**TC010 cancel behaviour**
SauceDemo's Cancel button on step 2 navigates to the inventory page (not back to cart or step 1). `CheckoutStepTwoPage::clickCancel()` returns an `InventoryPage` and TC010 asserts `isOnInventoryPage()` to match the actual application behaviour.

---

---

# Part 2 — Performance Tests

Browser-based performance tests that drive the full order placement flow through a real Chromium instance, measuring how long each step takes under concurrent user load.

## Why browser-based performance?

SauceDemo is a pure React SPA — the entire checkout flow (adding items, filling the form, placing the order) executes client-side in JavaScript. HTTP-level load tools (JMeter, Gatling, k6 HTTP) can only request static assets; they cannot interact with React state, fill the checkout form, or verify the confirmation page appeared. Only a real browser can exercise the actual flow.

## Tech Stack

| Component | Choice | Reason |
|---|---|---|
| Tool | k6 | Lightweight, code-driven, ships with the browser module bundled |
| Browser module | k6/browser (Chromium via CDP) | Drives a real browser without needing Selenium |
| Script language | JavaScript (ES6+) | k6's native language |

## Prerequisites

```bash
brew install k6   # macOS — includes the browser module
# or: https://k6.io/docs/get-started/installation/
```

No Selenium Server needed. k6 launches Chromium itself.

## What the Performance Script Does

`performance/checkout-flow.js` executes the full order placement flow once per VU iteration:

```
Open SauceDemo → Login → Add product to cart → Navigate to cart
→ Proceed to checkout → Fill personal info form → Continue to review
→ Place order → Verify confirmation page
```

This mirrors TC007 (`testFullOrderPlacementHappyPath`) step for step, using the same CSS selectors defined in `Config.php`.

## Scenarios

Three named scenarios are defined in the script. Select one via the `SCENARIO` environment variable:

| Scenario | VUs | Duration | Purpose |
|---|---|---|---|
| `smoke` | 1 VU, 1 iteration | ~60 s max | Validate the script runs end-to-end without errors — always run this first |
| `load` | 3 VUs, constant | 1 minute | Establish a stable baseline and capture realistic p50 / p95 percentiles |
| `stress` | ramp 1 → 6 → 0 | ~2 minutes | Find the concurrency level at which step timings begin to degrade |

VU counts are conservative because each VU runs a real Chromium instance — 8+ concurrent browsers will exhaust laptop memory.

## Running the Performance Tests

```bash
# Always start with smoke to validate the script before a longer run
SCENARIO=smoke k6 run performance/checkout-flow.js

# Baseline load — 3 concurrent browser users for 1 minute
SCENARIO=load k6 run performance/checkout-flow.js

# Stress — ramp from 1 to 6 concurrent users, observe where timings degrade
SCENARIO=stress k6 run performance/checkout-flow.js

# Headed browser — opens a visible Chrome window (useful for debugging)
SCENARIO=smoke K6_BROWSER_HEADLESS=false k6 run performance/checkout-flow.js

# Export raw results as JSON for further analysis
SCENARIO=load k6 run --out json=build/reports/k6-results.json performance/checkout-flow.js
```

## Metrics

### Custom step-level timings

These are recorded by the script for every iteration and appear in the `CUSTOM` section of the k6 summary:

| Metric | What the timer starts at | What the timer stops at |
|---|---|---|
| `checkout_login_duration` | `page.goto(BASE_URL)` | Inventory page's Add-to-cart button visible |
| `checkout_cart_duration` | Cart link click | First cart item visible |
| `checkout_step1_duration` | Checkout button click | Order summary (`.summary_info`) visible |
| `checkout_step2_duration` | Finish button click | Confirmation header (`.complete-header`) visible |
| `checkout_e2e_duration` | `page.goto(BASE_URL)` | Confirmation header visible |

### Core Web Vitals (collected automatically per page load)

| Metric | What it measures | Google "Good" threshold | Stress run (avg) |
|---|---|---|---|
| `browser_web_vital_fcp` | First Contentful Paint — time until first content is rendered | < 1,800 ms | ✅ 417 ms |
| `browser_web_vital_lcp` | Largest Contentful Paint — time until main content is rendered | < 2,500 ms | ✅ 560 ms |
| `browser_web_vital_cls` | Cumulative Layout Shift — visual stability (0 = no shifting) | < 0.1 | ✅ 0.002 |
| `browser_web_vital_ttfb` | Time to First Byte — server response latency | < 800 ms | ✅ 152 ms |
| `browser_web_vital_inp` | Interaction to Next Paint — UI responsiveness to clicks | < 200 ms | ✅ 33 ms |
| `browser_web_vital_fid` | First Input Delay — main thread responsiveness on first click | < 100 ms | ✅ 0.17 ms |

> Results captured during the `stress` scenario (ramp 1 → 6 VUs, 94 iterations, 100% checks passed).

### Thresholds (pass / fail gates)

The run exits with a non-zero status code if any threshold is breached:

| Metric | Threshold | Meaning |
|---|---|---|
| `checkout_e2e_duration` | p(95) < 20,000 ms | 95% of full flows complete in under 20 s |
| `checkout_step1_duration` | p(95) < 8,000 ms | 95% of form fills + continue complete in under 8 s |
| `checkout_step2_duration` | p(95) < 5,000 ms | 95% of finish → confirmation complete in under 5 s |
| `browser_web_vital_lcp` | p(75) < 2,500 ms | 75% of page loads meet Google's LCP "good" threshold |
| `browser_web_vital_fcp` | p(75) < 1,800 ms | 75% of page loads meet Google's FCP "good" threshold |
| `checks` | rate > 95% | At least 95% of all assertions pass across all iterations |

### Understanding percentiles

Percentiles answer: *"what value were X% of measurements at or below?"*

| Percentile | Meaning |
|---|---|
| p(50) / median | What the typical user experiences |
| p(90) | What a "bad luck" user experiences — only 10% were slower |
| p(95) | Standard SLA target — only 5% were slower |
| p(99) | Worst-case outlier threshold — only 1% were slower |

Average is not used as a primary metric because a single slow outlier (timeout, network blip) skews it significantly without reflecting typical user experience.
