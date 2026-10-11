<?php

namespace NoizuLabs\PHPConform\PHPUnitExtension;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\IncompleteTestError;
use PHPUnit\Framework\SkippedTest;

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
        if ($result['result'] !== 'Pending' || isset($result['exception'])) {
            // Pending WITH an exception means the step matched and called
            // markTestIncomplete(): the match is consumed, do not retry in
            // contained modules.
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
                        foreach ($step['varmap'] as $ignored) {
                            $args[] = $matches[$i];
                            $i++;
                        }
                        call_user_func_array([$step['class'], $step['function']], $args);
                    } else {
                        // Pass the preg_match() result positionally so the
                        // step method's parameter name is irrelevant.
                        call_user_func_array([$step['class'], $step['function']], [$matches]);
                    }
                    $asserts = $this->snapshotAssertCount();
                    $response = ['result' => 'Passed', 'asserts' => $asserts, 'exception' => null];
                } catch (AssertionFailedError $e) {
                    $asserts = $this->snapshotAssertCount();
                    $this->_failed = true;
                    $this->_failure = $e;
                    $response = [
                        'result' => $this->softResultFor($e),
                        'asserts' => $asserts,
                        'exception' => $e,
                    ];
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
                    $response = [
                        'result' => $this->softResultFor($e),
                        'asserts' => $asserts,
                        'exception' => $e,
                    ];
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

    /**
     * Steps that call markTestIncomplete()/markTestSkipped() throw
     * AssertionFailedError subclasses; surface their intent instead of a
     * generic failure.
     */
    private function softResultFor(\Exception $e): string
    {
        if ($e instanceof IncompleteTestError) {
            return 'Pending';
        }
        if ($e instanceof SkippedTest) {
            return 'Skipped';
        }
        return 'Failed';
    }

    public function registerSteps(): void
    {
        $this->registerClassSteps($this);
    }

    /**
     * Scan a class for step definitions declared with the docblock
     * annotations ("given"/"when"/"then" keyword sentences, optional
     * "pregmatch" mode, dollar-sign token binding) or the PHP 8
     * attributes (Given/When/Then, optional PregMatch).
     */
    public function registerClassSteps(object $targetClass): void
    {
        $reflection = new \ReflectionClass($targetClass);
        $methods = $reflection->getMethods();
        $matches = [];

        foreach ($methods as $method) {
            // Never register the library's own plumbing as steps.
            $declaring = $method->getDeclaringClass()->getName();
            if ($declaring === StepAbstract::class || $declaring === StepRunner::class) {
                continue;
            }

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
                // Single-line docblocks ("/** @given a calculator */") leave the
                // comment terminator in the captured sentence; strip it.
                $sentence = ucfirst($matches[1]) . ' ' .
                    trim((string) preg_replace('#\*/\s*$#', '', $matches[2]));
            }

            $varmap = null;
            foreach ($method->getAttributes() as $attribute) {
                $name = $attribute->getName();
                $pos = strrpos($name, '\\');
                $short = $pos === false ? $name : substr($name, $pos + 1);
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
