<?php

namespace NoizuLabs\PHPConform\PHPUnitExtension;

use PHPUnit\Framework\AssertionFailedError;

abstract class StepAbstract extends Module
{
    protected array $_containedSteps = [];
    protected array $_regexMap = [];
    protected bool $_failed = false;
    protected mixed $_failure = null;
    protected string $_scenario = '';
    protected ?string $_lastAction = null;

    public function __construct()
    {
        $this->registerSteps();
    }

    public function setScenario(string $scenario): void
    {
        $this->_scenario = $scenario;
        foreach ($this->_containedSteps as $key => $value) {
            $this->_containedSteps[$key]->setScenario($scenario);
        }
    }

    public function setUp(): void
    {
        $this->_lastAction = null;
    }

    public function tearDown(): void
    {
    }

    /**
     * Match a plain-language step ("Given a calculator", "And the total
     * should be 5", ...) against the registered step definitions and run
     * the first match.
     *
     * @return array{result: string, asserts: int, exception: ?\Exception}
     */
    public function executeStep(string $stepString): array
    {
        $lowered = strtolower($stepString);
        if (str_starts_with($lowered, 'given')) {
            $this->_lastAction = 'Given';
        } elseif (str_starts_with($lowered, 'when')) {
            $this->_lastAction = 'When';
        } elseif (str_starts_with($lowered, 'then')) {
            $this->_lastAction = 'Then';
        } elseif (str_starts_with($lowered, 'and') || str_starts_with($lowered, 'but')) {
            if ($this->_lastAction === null) {
                trigger_error('And or But may only be used after Given, When or Then', E_USER_ERROR);
            } else {
                $stepString = $this->_lastAction . substr($stepString, 3);
            }
        }

        $result = $this->executeLocalSteps($stepString);
        if ($result['result'] !== 'Pending') {
            return $result;
        }

        return $this->executeContainedSteps($stepString);
    }

    public function addStepModule(StepAbstract $stepSuite): void
    {
        $this->_containedSteps[] = $stepSuite;
    }

    protected function executeLocalSteps(string $stepString): array
    {
        foreach ($this->_regexMap as $step) {
            if ($step['regex']) {
                $matches = [];
                if (!preg_match($step['string'], $stepString, $matches)) {
                    continue;
                }
                try {
                    if (isset($step['varmap'])) {
                        $args = [];
                        $i = 1;
                        foreach ($step['varmap'] as $key => $value) {
                            $args[] = $matches[$i];
                            $i++;
                        }
                        call_user_func_array([$step['class'], $step['function']], $args);
                    } else {
                        call_user_func_array(
                            [$step['class'], $step['function']],
                            ['matches' => $matches]
                        );
                    }
                    $asserts = $this->snapshotAssertCount();
                    $response = ['result' => 'Passed', 'asserts' => $asserts, 'exception' => null];
                } catch (AssertionFailedError $e) {
                    $asserts = $this->snapshotAssertCount();
                    $this->_failed = true;
                    $this->_failure = $e;
                    $response = ['result' => 'Failed', 'asserts' => $asserts, 'exception' => $e];
                } catch (\Exception $e) {
                    $asserts = $this->snapshotAssertCount();
                    $this->_failed = true;
                    $this->_failure = $e;
                    $response = ['result' => 'Exception', 'asserts' => $asserts, 'exception' => $e];
                }
                return $response;
            }

            if ($step['string'] === $stepString) {
                try {
                    call_user_func_array([$step['class'], $step['function']], []);
                    $asserts = $this->snapshotAssertCount();
                    $response = ['result' => 'Passed', 'asserts' => $asserts, 'exception' => null];
                } catch (AssertionFailedError $e) {
                    $asserts = $this->snapshotAssertCount();
                    $this->_failed = true;
                    $this->_failure = $e;
                    $response = ['result' => 'Failed', 'asserts' => $asserts, 'exception' => $e];
                } catch (\Exception $e) {
                    $asserts = $this->snapshotAssertCount();
                    $this->_failed = true;
                    $this->_failure = $e;
                    $response = ['result' => 'Exception', 'asserts' => $asserts, 'exception' => $e];
                }
                return $response;
            }
        }

        return ['result' => 'Pending', 'asserts' => $this->snapshotAssertCount(), 'exception' => null];
    }

    protected function executeContainedSteps(string $stepString): array
    {
        foreach ($this->_containedSteps as $stepRunner) {
            $response = $stepRunner->executeStep($stepString);
            if ($response['result'] !== 'Pending') {
                return $response;
            }
        }

        return ['result' => 'Pending', 'asserts' => $this->snapshotAssertCount(), 'exception' => null];
    }

    private function snapshotAssertCount(): int
    {
        $asserts = $this->assertionsPerformed();
        $this->resetAssertionCount();
        return $asserts;
    }

    public function registerSteps(): void
    {
        $this->registerClassSteps($this);
    }

    /**
     * Scan a class for @given/@when/@then docblock step definitions.
     *
     * Three binding styles are supported:
     *  - exact sentence match:            @when running phpunit
     *  - pregmatch + capture groups:      @pregmatch # ... (.*) ...   -> $matches
     *  - dollar-sign token binding:       @when I add $arg1 plus $arg2
     *    where each $token becomes a ([^\s]*) capture bound, in order,
     *    to the method's parameters.
     *
     * As an alternative to docblocks, step definitions may also be declared
     * with PHP 8 attributes: #[Given('a calculator')], #[When('...')],
     * #[Then('...')] and #[PregMatch('/')]. Docblock support is unchanged;
     * attributes are additive.
     */
    public function registerClassSteps(object $targetClass): void
    {
        $reflection = new \ReflectionClass($targetClass);
        $methods = $reflection->getMethods();
        $matches = [];

        foreach ($methods as $method) {
            $doc = $method->getDocComment();
            $doc = $doc === false ? '' : $doc;
            $regexp = false;
            $delim = '/';

            if (preg_match('/@pregmatch (.)/', $doc, $matches)) {
                $regexp = true;
                $delim = $matches[1] ?? '/';
            }

            $sentence = '';

            if (preg_match('/@([tT]hen|[wW]hen|[gG]iven) (.*)/', $doc, $matches)) {
                $sentence = ucfirst($matches[1]) . ' ' . trim($matches[2]);
            }

            $varmap = null;
            foreach ($method->getAttributes() as $attribute) {
                $name = $attribute->getName();
                $short = substr($name, (int) strrpos($name, '\\'));
                $args = $attribute->getArguments();
                $text = isset($args[0]) && is_string($args[0]) ? $args[0] : '';
                if ($short === 'PregMatch' && $text !== '') {
                    $regexp = true;
                    $delim = $text;
                } elseif (in_array($short, ['Given', 'When', 'Then'], true) && $text !== '' && $sentence === '') {
                    $sentence = $short . ' ' . trim($text);
                }
            }

            if ($sentence === '') {
                $function = $method->getShortName();
                if (preg_match('/^([tT]hen|[wW]hen|[gG]iven) (.*)/', $function, $matches)) {
                    $sentence = ucfirst($matches[1]) . ' ' . trim($matches[2]);
                }
            }

            if ($sentence === '') {
                continue;
            }

            // If the user has not specified regexp mode, look for dollar
            // signs in the sentence; when found, pop names onto the varmap.
            if (!$regexp) {
                if (preg_match_all('/(\s\$[^\s]*)/', $sentence, $matches)) {
                    $regexp = true;
                    $varmap = $matches[0];
                    $sentence = preg_replace('/(\s\$[^\s]*)/', ' ([^\s]*)', $sentence);
                }
            }

            if ($regexp) {
                $sentence = $delim . $sentence . $delim;
            }

            $this->_regexMap[] = [
                'regex' => $regexp,
                'string' => $sentence,
                'varmap' => $varmap,
                'class' => $targetClass,
                'function' => $method->getShortName(),
            ];
        }
    }
}
