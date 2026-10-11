# php-conform

BDD-style testing extensions for PHPUnit: write scenarios as plain-text
`.suite` specs, bind them to step classes with docblock annotations, and get
readable story output while they run.

Modernized for **PHP 8.2+** and **PHPUnit 11** (the newest PHPUnit line that
supports PHP 8.2; it runs on PHP 8.2–8.4).

## Install

```bash
composer require --dev noizu-labs/php-conform
```

## Quickstart

**1. Describe your story in a `.suite` file** (named after your test class in
snake_case, next to it — `CalculatorScenarioTest` → `calculator_scenario.suite`):

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

**2. Define steps in a class extending `StepAbstract`:**

```php
use NoizuLabs\PHPConform\PHPUnitExtension\StepAbstract;
use NoizuLabs\PHPConform\Tests\sut\Calculator;

class ExampleSteps extends StepAbstract
{
    /** @given a calculator */
    public function createCalculator()
    {
        $this->_calculator = new Calculator();
    }

    /** @when I add $a plus $b */
    public function Addition($a, $b)
    {
        $this->_calculator->add((int) $a, (int) $b);
    }

    /** @then the total should be $total */
    public function checkTotal($total)
    {
        $this->assertEquals((int) $total, $this->_calculator->equals());
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

- optional PHP 8 attributes alongside docblocks:
  `#[Given('a calculator')]`, `#[When('...')]`, `#[Then('...')]`,
  `#[PregMatch('#')]`. Docblocks remain the canonical form.

**3. Wire the spec into a test class extending `ScenarioSuite`:**

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

**4. Register the BDD output extension in `phpunit.xml`** (PHPUnit 10+
dropped result printers, so story output is an event extension):

```xml
<phpunit bootstrap="vendor/autoload.php" colors="true">
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
`[M]` multi (data-driven), `[A]` aborted after a failure.

## Selenium support (optional)

Scenario suites can drive a browser when `pimple/pimple` and
`instaclick/php-webdriver` are installed:

```bash
composer require --dev pimple/pimple instaclick/php-webdriver
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
