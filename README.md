# php-conform

BDD-style testing extensions for PHPUnit: write scenarios as plain-text
`.suite` specs, bind them to step classes with docblock annotations, and get
readable story output while they run.

Modernized for **PHP 8.2–8.5** and **PHPUnit 11, 12 and 13** (PHPUnit 12
needs PHP >= 8.3, PHPUnit 13 needs PHP >= 8.4; the combination matrix is
exercised in CI).

## Install

```bash
composer require --dev noizu-labs/php-conform
```

The package requires `phpunit/phpunit ^11.5 || ^12.0 || ^13.0` directly, so
composer installs a compatible PHPUnit alongside it — or refuses the
combination outright (for example on PHP 8.2, where PHPUnit 12+ cannot be
installed) instead of failing later at runtime.

## Quickstart

**1. Describe your story in a `.suite` file.** The file is named after your
test class with a trailing `Test` removed and the rest snake_cased, and sits
next to it in the same directory — `CalculatorScenarioTest` →
`calculator_scenario.suite`. (A `protected string $scenarioFile` property on
the suite class points somewhere else.)

```
#Story: Calculator
As a developer
I want to check arithmetic

#Scenario: Adding numbers
Given a calculator
When I add 3 plus 4
Then the total should be 7

#Scenario: Adding examples
Given a calculator
When I add <a> plus <b>
Then the total should be <c>

Examples:
    | a | b | c |
    | 1 | 2 | 3 |
    | 4 | 5 | 9 |
```

**2. Define steps in a class extending `StepAbstract`** (single-line or
multi-line docblocks both work):

```php
use NoizuLabs\PHPConform\PHPUnitExtension\StepAbstract;

class ExampleSteps extends StepAbstract
{
    protected int $total = 0;

    /** @given a calculator */
    public function createCalculator()
    {
        $this->total = 0;
    }

    /** @when I add $a plus $b */
    public function Addition($a, $b)
    {
        $this->total = (int) $a + (int) $b;
    }

    /** @then the total should be $total */
    public function checkTotal($total)
    {
        $this->assertEquals((int) $total, $this->total);
    }
}
```

Step sentences support:

- exact sentence matching (`@given a calculator`),
- dollar-sign token binding (`@when I add $a plus $b` — each `$token`
  becomes a `([^\s]*)` capture bound, in order, to the method parameters),
- full regular expressions with capture groups:

  ```php
  /**
   * @pregmatch #
   * @when we call a step function that stores the value (.*)
   */
  public function storeValue($matches) { /* $matches[1] */ }
  ```

  The full `preg_match()` result array is passed as the method's **first
  parameter, positionally** — any parameter name works (`$matches` is the
  convention; `$matches[0]` is the whole step sentence, `$matches[1]` the
  first capture group).

- optional PHP 8 attributes alongside docblocks:
  `#[Given('a calculator')]`, `#[When('...')]`, `#[Then('...')]`,
  `#[PregMatch('#')]`. Docblocks remain the canonical form.

**3. Make your step and suite classes autoloadable.** PHPUnit only loads
`*Test.php` files itself; step classes and any other helpers must be
reachable through composer's autoloader. The simplest way is a classmap over
your tests directory — add this to `composer.json` and regenerate:

```json
"autoload-dev": {
    "classmap": ["tests/"]
}
```

```bash
composer dump-autoload
```

**4. Wire the spec into a test class extending `ScenarioSuite`:**

```php
use NoizuLabs\PHPConform\PHPUnitExtension\ScenarioSuite;

/**
 * @stepClass ExampleSteps
 */
class CalculatorScenarioTest extends ScenarioSuite
{
}
```

Each `#Scenario:` block becomes one PHPUnit test (named after the
scenario). Attach extra step modules per scenario with a `@stepClass`
annotation on a method named after the scenario in camelCase:

```php
/**
 * @stepClass StepsForJustThisScenario
 */
public function addingExamples()
{
}
```

**5. Point PHPUnit at your tests and register the BDD output extension in
`phpunit.xml`** (PHPUnit 10+ dropped result printers, so story output is an
event extension). Without a `<testsuites>` entry PHPUnit has nothing to run:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true" cacheDirectory=".phpunit-cache">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <extensions>
        <bootstrap class="\NoizuLabs\PHPConform\PHPUnitExtension\BDDExtension"/>
    </extensions>
</phpunit>
```

The extension accepts a `colors` parameter (`never`, `always` or `auto`).

## Run

```bash
vendor/bin/phpunit
```

Story blocks are printed as each scenario finishes; `--no-progress`
gives clean story-only output:

```
**************************************************
** Story: Adding numbers (Passed)               **
**************************************************

  Steps:
    [+] Given a calculator
    [+] When I add 3 plus 4
    [+] Then the total should be 7

  Scenario Details:
    Total Asserts:1
```

Step statuses: `[+]` passed, `[-]` failed, `[P]` pending (no matching step
definition — the scenario is reported incomplete), `[S]` skipped,
`[M]` multi (data-driven), `[A]` aborted — remaining steps after a step
that failed **or** was skipped are marked aborted instead of being run
(the Selenium scenario shows this: the skipped step is `[S]`, the steps
after it are `[A]`, with no failure anywhere).

## Selenium support (optional)

Scenario suites can drive a browser when `pimple/pimple` and
`php-webdriver/webdriver` (the maintained community fork, same `WebDriver\`
namespace as the abandoned facebook/instaclick clients) are installed:

```bash
composer require --dev pimple/pimple php-webdriver/webdriver
```

Provide a container in your bootstrap and call `$this->getWebDriver()` from
a step. Without the packages, scenarios that need a browser should call
`$this->markTestSkipped(...)` (see the example suite).

## Development

```bash
composer install
composer test        # runs vendor/bin/phpunit
```

The repository's own suite (`test/`) doubles as a working example of every
DSL feature.

## License

MIT — see `license`.
