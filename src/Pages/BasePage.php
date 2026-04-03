<?php

declare(strict_types=1);

namespace SauceDemo\Pages;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverExpectedCondition;
use SauceDemo\Config;

/**
 * Base class for all Page Objects.
 *
 * Provides explicit-wait helpers so individual page objects never call
 * findElement() without first ensuring the element is ready. No assertions
 * live here — only WebDriver interactions.
 */
abstract class BasePage
{
    public function __construct(protected RemoteWebDriver $driver)
    {
    }

    /**
     * Wait until the element matching $cssSelector is present in the DOM,
     * then return it. Use when you need the element's text or attributes
     * before it is necessarily visible.
     */
    protected function waitForElement(string $cssSelector): WebDriverElement
    {
        $this->driver->wait(Config::explicitWait())->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector($cssSelector)
            )
        );

        return $this->driver->findElement(WebDriverBy::cssSelector($cssSelector));
    }

    /**
     * Wait until the element is both present and visible, then return it.
     * Use when the test must interact with or assert on something the user
     * can actually see.
     */
    protected function waitForVisible(string $cssSelector): WebDriverElement
    {
        $this->driver->wait(Config::explicitWait())->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(
                WebDriverBy::cssSelector($cssSelector)
            )
        );

        return $this->driver->findElement(WebDriverBy::cssSelector($cssSelector));
    }

    /**
     * Wait until the element is clickable, then click it.
     */
    protected function waitAndClick(string $cssSelector): void
    {
        $this->driver->wait(Config::explicitWait())->until(
            WebDriverExpectedCondition::elementToBeClickable(
                WebDriverBy::cssSelector($cssSelector)
            )
        );

        $this->driver->findElement(WebDriverBy::cssSelector($cssSelector))->click();
    }

    /**
     * Fill a React-controlled input field reliably.
     *
     * WebDriver's sendKeys() dispatches keyboard events but does not always
     * trigger React's synthetic onChange, leaving the component state empty
     * even though the field appears filled visually. This method uses the
     * native HTMLInputElement value setter to bypass React's proxy and then
     * fires both 'input' and 'change' events so React updates its state.
     *
     * Use this instead of sendKeys() for any <input> that is part of a
     * React-controlled form.
     */
    protected function fillInput(string $cssSelector, string $value): void
    {
        $element = $this->waitForVisible($cssSelector);

        $this->driver->executeScript(
            "var el = arguments[0];
             var setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
             setter.call(el, arguments[1]);
             el.dispatchEvent(new Event('input',  { bubbles: true }));
             el.dispatchEvent(new Event('change', { bubbles: true }));",
            [$element, $value]
        );

        // Poll until the field's value attribute matches what we set.
        // This confirms React has processed the dispatched events and updated
        // its internal state before the caller proceeds (e.g. clicks Continue).
        // Under load (e.g. multiple items in the cart), React may need a few
        // extra milliseconds to flush its event queue.
        $this->driver->wait(Config::explicitWait())->until(
            function ($driver) use ($cssSelector, $value) {
                return $driver->findElement(
                    WebDriverBy::cssSelector($cssSelector)
                )->getAttribute('value') === $value;
            }
        );
    }

    /**
     * Wait until the current URL contains $partial.
     */
    protected function waitForUrl(string $partial): void
    {
        $this->driver->wait(Config::explicitWait())->until(
            WebDriverExpectedCondition::urlContains($partial)
        );
    }

    public function getCurrentUrl(): string
    {
        return $this->driver->getCurrentURL();
    }
}
