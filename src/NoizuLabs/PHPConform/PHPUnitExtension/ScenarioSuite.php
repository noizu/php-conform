<?php

namespace NoizuLabs\PHPConform\PHPUnitExtension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\IncompleteTestError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\SkippedWithMessageException;
use RuntimeException;

/**
 * The ScenarioSuite class provides the hook PHPUnit needs to run a BDD
 * .suite file: each scenario in the file becomes one PHPUnit test.
 *
 * Modern PHPUnit (10+) removed TestListener/result-printer plugins and made
 * TestCase::run() final, so the old reflection shunt into PHPUnit's result
 * object is gone. Instead:
 *
 *  - scenarios are surfaced through a static data provider
 *    (one PHPUnit test per #Scenario: block, named after the scenario), and
 *  - the story/step rendering is emitted by the event-driven BDDExtension
 *    (see BDDExtension.php), registered from phpunit.xml.
 */
#[\AllowDynamicProperties]
abstract class ScenarioSuite extends TestCase
{
    /** @var array<class-string, array{scenarios: array<string, list<string>>, examples: array<string, array<string, list<string>>>, story: list<string>, storyName: string}> */
    private static array $parsedSuites = [];

    protected mixed $container = null;
    protected mixed $webdriver = null;
    public mixed $Story;
    public mixed $Scenario;

    /** @var array<string, list<string>> */
    protected array $_scenarios = [];
    protected string|false $_storyName = false;
    /** @var array<string, array<string, list<string>>> */
    protected array $_examples = [];
    protected ?StepAbstract $_stepRunner = null;
    /** @var array<string, mixed> */
    public array $_noizuVariableSpace = [];
    public mixed $_scenario;
    private bool $scenarioStateInitialized = false;

    /**
     * PHPUnit 12 made TestCase::__construct() final, so the parsed suite,
     * container wiring and step-module registration happen lazily, before
     * anything needs them.
     */
    private function initializeScenarioState(): void
    {
        if ($this->scenarioStateInitialized) {
            return;
        }
        $this->scenarioStateInitialized = true;

        $parsed = self::parsedSuiteFor(static::class);
        $this->_scenarios = $parsed['scenarios'];
        $this->_examples = $parsed['examples'];
        $this->_storyName = $parsed['storyName'];

        global $noizulabs_phpconform_container;
        if (isset($noizulabs_phpconform_container)) {
            $this->container = $noizulabs_phpconform_container;
        } elseif (class_exists(\Pimple\Container::class)) {
            $this->container = new \Pimple\Container();
        }

        $this->loadSteps();
    }

    /**
     * Data provider feeding one PHPUnit test per #Scenario: block in the
     * suite file. PHPUnit resolves providers at suite-build time, before
     * any instance exists, so this must be static.
     *
     * @return array<string, list{string}>
     */
    public static function provideScenarios(): array
    {
        $parsed = self::parsedSuiteFor(static::class);
        $provider = [];
        foreach (array_keys($parsed['scenarios']) as $scenario) {
            $provider[$scenario] = [$scenario];
        }
        return $provider;
    }

    /**
     * The single PHPUnit test method; runs the scenario named by the
     * current data set and maps its outcome onto PHPUnit's outcome model.
     */
    #[DataProvider('provideScenarios')]
    public function testScenario(string $scenarioName): void
    {
        $this->initializeScenarioState();

        $steps = $this->_scenarios[$scenarioName] ?? [];
        $examples = $this->_examples[$scenarioName] ?? [];

        $additionalSteps = $this->loadScenarioSteps($scenarioName);
        $stepRunner = new StepRunner($additionalSteps, $this->_stepRunner, $this);

        $test = new Scenario((string) $this->_storyName, $scenarioName, $steps, $stepRunner);
        $this->Scenario = $scenarioName;
        $this->Story = $this->_storyName;

        $outcome = $test->executeScenario($this, $examples);
        $outcome['scenario'] = $scenarioName;

        // Record the story details for the BDD output extension (which
        // renders after PHPUnit has counted assertions for this test).
        ScenarioResultRegistry::record(static::class, 'testScenario', $outcome);

        if ($outcome['asserts'] > 0) {
            $this->addToAssertionCount((int) $outcome['asserts']);
        } else {
            $this->expectNotToPerformAssertions();
        }

        switch ($outcome['status']) {
            case 'Passed':
                return;

            case 'Pending':
                throw $outcome['exception']
                    ?? new IncompleteTestError('Scenario Not Implemented: ' . $scenarioName);

            case 'Skipped':
                throw $outcome['exception']
                    ?? new SkippedWithMessageException('Scenario Skipped: ' . $scenarioName);

            case 'Failed':
            case 'Exception':
            default:
                throw $outcome['exception']
                    ?? new RuntimeException('Unknown exception occurred in scenario: ' . $scenarioName);
        }
    }

    public function getWebDriver(?string $alternativeHost = null): mixed
    {
        $this->initializeScenarioState();

        if ($this->webdriver === null) {
            if (isset($this->container) && isset($this->container['WebDriver'])) {
                $host = is_null($alternativeHost) ? $this->container['SeleniumHost'] : $alternativeHost;
                $factory = $this->container['WebDriver'];
                $this->webdriver = $factory($host);
            }
        }

        if (is_null($this->webdriver)) {
            throw new \Exception("WebDriver not available from \$this->container['WebDriver']");
        }
        return $this->webdriver;
    }

    public function &getReference(string $arg): mixed
    {
        if (!property_exists($this, $arg)) {
            $this->{$arg} = null;
        }
        return $this->{$arg};
    }

    public function getSuite(): string|false
    {
        $this->initializeScenarioState();

        return $this->_storyName;
    }

    protected function loadSteps(): void
    {
        $stepClasses = $this->classAnnotation('stepClass');

        if (!empty($stepClasses)) {
            $this->_stepRunner = $this->register(new StepRunner());
            foreach ($stepClasses as $class) {
                $resolved = $this->resolveStepClass($class);
                $runner = $this->register(new $resolved(), true);
                $this->_stepRunner->addStepModule($runner);
            }
        } else {
            $this->_stepRunner = null;
        }
    }

    /**
     * Load step modules attached (via @stepClass method annotations) to the
     * method named after a scenario, e.g. scenario "Step Functions" looks up
     * stepFunctions().
     */
    protected function loadScenarioSteps(string $name): ?StepRunner
    {
        $stepRunner = null;
        $method = $this->sentenceToCamelCase($name);

        if (method_exists($this, $method)) {
            $stepClasses = $this->methodAnnotation($method, 'stepClass');
            if (!empty($stepClasses)) {
                $stepRunner = $this->register(new StepRunner());
                foreach ($stepClasses as $class) {
                    $resolved = $this->resolveStepClass($class);
                    $runner = $this->register(new $resolved(), true);
                    $stepRunner->addStepModule($runner);
                }
            }
        }
        return $stepRunner;
    }

    public function register(Module $mod, bool $directWrite = false): Module
    {
        $mod->register($this, $directWrite);
        return $mod;
    }

    /**
     * Resolve a @stepClass annotation value to a loadable class name:
     * the literal name first, then the same namespace as the suite.
     */
    protected function resolveStepClass(string $class): string
    {
        if (class_exists($class)) {
            return $class;
        }
        $namespace = (new \ReflectionClass($this))->getNamespaceName();
        if ($namespace !== '' && class_exists($namespace . '\\' . $class)) {
            return $namespace . '\\' . $class;
        }
        throw new RuntimeException("Step class '$class' referenced via @stepClass could not be loaded");
    }

    /**
     * Values for a single class-level annotation, e.g. @stepClass.
     *
     * @return list<string>
     */
    protected function classAnnotation(string $name): array
    {
        $reflection = new \ReflectionClass($this);
        $doc = $reflection->getDocComment();
        return $doc === false ? [] : $this->extractAnnotation($doc, $name);
    }

    /**
     * Values for a single method-level annotation, e.g. @stepClass.
     *
     * @return list<string>
     */
    protected function methodAnnotation(string $method, string $name): array
    {
        try {
            $reflection = new \ReflectionMethod($this, $method);
        } catch (\ReflectionException) {
            return [];
        }
        $doc = $reflection->getDocComment();
        return $doc === false ? [] : $this->extractAnnotation($doc, $name);
    }

    /** @return list<string> */
    private function extractAnnotation(string $doc, string $name): array
    {
        $values = [];
        if (preg_match_all('/@' . preg_quote($name, '/') . '\s+(\S+)/', $doc, $matches)) {
            $values = $matches[1];
        }
        return $values;
    }

    protected function sentenceToCamelCase(string $sentence): string
    {
        $sentence = trim($sentence);
        $sentence = preg_replace('/\s\s?/', ' ', $sentence) ?? $sentence;
        $words = explode(' ', $sentence);
        $cs = '';
        foreach ($words as $word) {
            $cs .= ucfirst($word);
        }
        return lcfirst($cs);
    }

    /**
     * Parse (once per class) the .suite file backing a ScenarioSuite class.
     *
     * @param class-string $class
     *
     * @return array{scenarios: array<string, list<string>>, examples: array<string, array<string, list<string>>>, story: list<string>, storyName: string}
     */
    protected static function parsedSuiteFor(string $class): array
    {
        if (isset(self::$parsedSuites[$class])) {
            return self::$parsedSuites[$class];
        }

        $file = self::suiteFileFor($class);
        $full = $file;
        $contents = file_get_contents($full);
        if ($contents === false) {
            throw new RuntimeException("Unable to read scenario suite file: $full");
        }

        $parsed = self::parseScenarioSuite($contents, $full);
        $parsed['storyName'] ??= '';

        self::$parsedSuites[$class] = $parsed;
        return $parsed;
    }

    /**
     * Derive the .suite file path for a suite class: the class name in
     * snake_case (trailing "Test" dropped) next to the declaring file,
     * unless the subclass defines a public string $scenarioFile property.
     *
     * @param class-string $class
     */
    protected static function suiteFileFor(string $class): string
    {
        $reflection = new \ReflectionClass($class);
        $scenarioDir = dirname($reflection->getFileName());

        if ($reflection->hasProperty('scenarioFile')) {
            $props = $reflection->getDefaultProperties();
            if (isset($props['scenarioFile']) && is_string($props['scenarioFile'])) {
                return $props['scenarioFile'];
            }
        }

        $short = $class;
        if (str_contains($short, '\\')) {
            $parts = explode('\\', $short);
            $short = (string) end($parts);
        }
        $short = preg_replace('/Test$/', '', $short) ?? $short;

        $file = strtolower(self::camelToSnake($short));
        return $scenarioDir . '/' . $file . '.suite';
    }

    private static function camelToSnake(string $class): string
    {
        $file = $class[0];
        $len = strlen($class);
        for ($i = 1; $i < $len; $i++) {
            if (ctype_upper($class[$i]) && !ctype_upper($class[$i - 1])) {
                $file .= '_';
            } elseif ($i > 1
                && !ctype_upper($class[$i])
                && ctype_upper($class[$i - 1])
                && ctype_upper($class[$i - 2])
            ) {
                $file = substr($file, 0, strlen($file) - 1)
                    . '_' . $class[$i - 1];
            }
            $file .= $class[$i];
        }
        return $file;
    }

    /**
     * Parse the .suite spec format:
     *
     *   #Story: <story title>
     *   <free-text story description>
     *
     *   #Scenario: <scenario title>
     *   Given ...
     *   When ...
     *   Then ...
     *   And ... / But ...
     *
     *   Examples:
     *       | col1 | col2 |
     *       | a    | b    |
     *
     * @return array{scenarios: array<string, list<string>>, examples: array<string, array<string, list<string>>>, story: list<string>, storyName: string}
     */
    public static function parseScenarioSuite(string $contents, string $fullFile): array
    {
        $lines = explode("\n", $contents);
        $mode = null;
        $lastCommand = null;
        $scenarios = [];
        $examples = [];
        $story = [];
        $storyName = '';
        $scenario = null;

        // $pointer targets either $scenarios[<name>], $examples[<name>] or $story
        $pointer = null;
        $emptyState = null;

        $headerLine = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/#Scenario:(.*)/', $line, $matches)) {
                $mode = 'Scenario';
                $lastCommand = null;
                $scenario = trim($matches[1]);
                if (isset($scenarios[$scenario])) {
                    print("Duplicate Test Case Found ($scenario)\n in $fullFile\n\n");
                    trigger_error("duplicate scenario definition for ($scenario) in $fullFile\n", E_USER_WARNING);
                    $pointer = &$emptyState;
                } else {
                    $scenarios[$scenario] = [];
                    $pointer = &$scenarios[$scenario];
                }
            } elseif (preg_match('/#Story:(.*)/', $line, $matches)) {
                $mode = 'Story';
                $lastCommand = null;
                $camel = preg_replace('/\s\s?/', ' ', trim($matches[1]));
                $words = explode(' ', (string) $camel);
                $cs = '';
                foreach ($words as $word) {
                    $cs .= ucfirst($word);
                }
                $storyName = lcfirst($cs);
                if (!empty($story)) {
                    print("multiple Story definitions found in $fullFile\n");
                    trigger_error("multiple Story definitions found in $fullFile\n", E_USER_WARNING);
                    $pointer = &$emptyState;
                } else {
                    $story = [];
                    $pointer = &$story;
                }
            } elseif (preg_match('/Examples:/', $line)) {
                $mode = 'Example';
                $lastCommand = null;
                $headerLine = false;
                if ($scenario === null) {
                    trigger_error("Examples block found outside of a scenario in $fullFile\n", E_USER_WARNING);
                    continue;
                }
                $examples[$scenario] = [];
                $pointer = &$examples[$scenario];
            } else {
                if ($mode === 'Example') {
                    if ($headerLine !== false) {
                        if ($line !== '') {
                            $values = explode('|', $line);
                            foreach ($headerLine as $k => $v) {
                                $pointer[$v][] = trim(isset($values[$k + 1]) ? $values[$k + 1] : '');
                            }
                        }
                    } else {
                        if ($line !== '') {
                            $headerLine = explode('|', $line);
                            array_shift($headerLine);
                            array_pop($headerLine);
                            foreach ($headerLine as $k => $v) {
                                $headerLine[$k] = trim($v);
                            }
                            foreach ($headerLine as $v) {
                                $pointer[$v] = [];
                            }
                        }
                    }
                } else {
                    if ($pointer !== null && strlen($line)) {
                        if (strpos($line, '#') !== 0 && strpos($line, '--') !== 0) {
                            if ($mode === 'Scenario') {
                                $lowered = strtolower($line);
                                if (str_starts_with($lowered, 'given')) {
                                    $lastCommand = 'Given';
                                } elseif (str_starts_with($lowered, 'when')) {
                                    $lastCommand = 'When';
                                } elseif (str_starts_with($lowered, 'then')) {
                                    $lastCommand = 'Then';
                                } elseif (str_starts_with($lowered, 'and') || str_starts_with($lowered, 'but')) {
                                    if (is_null($lastCommand)) {
                                        trigger_error("And must proceed Given,When or Then ($line)", E_USER_ERROR);
                                    }
                                }
                            }
                            $pointer[] = $line;
                        }
                    }
                }
            }
        }

        return [
            'scenarios' => $scenarios,
            'examples' => $examples,
            'story' => $story,
            'storyName' => $storyName,
        ];
    }
}
