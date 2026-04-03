<?php

declare(strict_types=1);

namespace SauceDemo\Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use PHPUnit\Framework\TestCase;
use SauceDemo\Config;
use SauceDemo\Pages\InventoryPage;
use SauceDemo\Pages\LoginPage;

/**
 * Base class for all test classes.
 *
 * Manages the WebDriver lifecycle: each test method receives a fresh browser
 * session (setUp / tearDown at method scope) to guarantee zero state pollution
 * between tests. A shared session would be faster but would risk carry-over
 * login state, cart contents, or session cookies between tests.
 */
abstract class BaseTest extends TestCase
{
    protected RemoteWebDriver $driver;
    protected LoginPage $loginPage;

    protected function setUp(): void
    {
        $this->driver = RemoteWebDriver::create(
            Config::seleniumHost() . '/wd/hub',
            $this->buildCapabilities(),
            5_000,   // connection timeout (ms)
            30_000   // request timeout (ms)
        );

        // Explicit waits only — see Config::explicitWait(). Mixing implicit
        // and explicit waits leads to non-deterministic timeout behaviour.
        $this->driver->manage()->timeouts()->implicitlyWait(0);
        $this->driver->manage()->window()->maximize();

        $this->loginPage = new LoginPage($this->driver);
    }

    protected function tearDown(): void
    {
        if (isset($this->driver)) {
            $this->driver->quit();
        }
    }

    // ── Shared helpers ────────────────────────────────────────────────────────

    /**
     * Log in as standard_user and return a loaded InventoryPage.
     * Used by CartTest and CheckoutTest to keep test methods focused on
     * what they are actually testing rather than the login preamble.
     */
    protected function loginAsStandardUser(): InventoryPage
    {
        return $this->loginPage
                    ->loginAs(Config::STANDARD_USER, Config::PASSWORD)
                    ->waitUntilLoaded();
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function buildCapabilities(): DesiredCapabilities
    {
        if (Config::browser() === 'firefox') {
            $caps = DesiredCapabilities::firefox();

            if (Config::headless()) {
                $opts = new FirefoxOptions();
                $opts->addArguments(['-headless']);
                $caps->setCapability(FirefoxOptions::CAPABILITY, $opts);
            }

            return $caps;
        }

        // Default: Chrome
        $caps = DesiredCapabilities::chrome();

        if (Config::headless()) {
            $opts = new ChromeOptions();
            $opts->addArguments([
                '--headless=new',
                '--disable-gpu',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--window-size=1920,1080',
            ]);
            $caps->setCapability(ChromeOptions::CAPABILITY_W3C, $opts);
        }

        return $caps;
    }
}
