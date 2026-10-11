<?php

namespace NoizuLabs\PHPConform\PHPUnitExtension;

use PHPUnit\Framework\IncompleteTest;
use PHPUnit\Framework\IncompleteTestError;
use PHPUnit\Framework\SkippedTest;

final class Scenario extends Module
{
    protected string $_name;
    protected array $_steps;
    protected StepAbstract $_stepRunner;
    protected string $_suite;

    public function __construct(string $suite, string $name, array $steps, StepAbstract $stepRunner)
    {
        $this->_suite = $suite;
        $this->_name = $name;
        $this->_steps = $steps;
        $this->_stepRunner = $stepRunner;
        $this->_stepRunner->setScenario($this->_name);
    }

    public function getName(): string
    {
        return $this->_name;
    }

    public function getSuite(): string
    {
        return $this->_suite;
    }

    /**
     * Execute this scenario against its step runner.
     *
     * When the scenario declares an Examples: table each row is executed
     * with its <placeholder> tokens substituted. The returned record
     * mirrors the structure the BDD printer renders (steps, per-row
     * statuses, exceptions, assertion totals).
     *
     * @param array<string, list<string>> $examples
     *
     * @return array<string, mixed>
     */
    public function executeScenario(ScenarioSuite $suite, array $examples = []): array
    {
        $record = [
            'steps' => [],
            'status' => 'Pending',
            'asserts' => 0,
        ];

        if (empty($examples)) {
            $resp = $this->execute($this->_steps);
            $record['asserts'] = (int) $resp['asserts'];
            $record['status'] = $resp['status'];
            $record['exception'] = $resp['exception'];
            $record['steps'] = $resp['steps'];
        } else {
            $record['multi'] = true;
            $record['multi-steps'] = ['status' => [], 'exception' => []];
            $record['examples'] = $examples;
            $replacements = array_keys($examples);
            $count = count($examples[$replacements[0]]);

            $raw = $this->_steps;
            $overall = 'Passed';

            for ($i = 0; $i < $count; $i++) {
                $rv = [];
                foreach ($replacements as $token) {
                    $rv[$token] = $examples[$token][$i];
                }
                $resp = $this->execute($raw, $rv);

                $record['asserts'] += (int) $resp['asserts'];
                $record['multi-steps']['status'][$i] = $resp['multi-steps']['status'];
                $record['multi-steps']['exception'][$i] = $resp['exception'];

                if ($resp['status'] !== 'Passed') {
                    $overall = $resp['status'];
                    if (!isset($record['exception']) && isset($resp['exception'])) {
                        $record['exception'] = $resp['exception'];
                    }
                }
            }
            $record['status'] = $overall;

            foreach ($raw as $v) {
                $record['steps'][] = ['step' => $v, 'status' => ['result' => 'Multi']];
            }
        }

        return $record;
    }

    /**
     * @param list<string>                $steps
     * @param array<string, string>       $replacements
     *
     * @return array<string, mixed>
     */
    protected function execute(array $steps, array $replacements = []): array
    {
        $noFailure = true;
        $response = [
            'asserts' => 0,
            'status' => null,
            'steps' => [],
            'exception' => null,
            'multi-steps' => ['status' => []],
        ];

        foreach ($replacements as $token => $value) {
            $response['multi-steps']['status'][$token] = 'Pending';
        }

        $status = ['result' => 'Passed', 'asserts' => 0, 'exception' => null];

        foreach ($steps as $step) {
            $tokens = [];

            foreach ($replacements as $token => $value) {
                if (strpos($step, "<$token>") !== false) {
                    $tokens[] = $token;
                }
                $step = str_replace("<$token>", $value, $step);
            }

            if ($noFailure) {
                $status = $this->_stepRunner->executeStep($step);
                $response['asserts'] += $status['asserts'];
                if ($status['result'] === 'Exception' && isset($status['exception'])) {
                    $response['exception'] ??= $status['exception'];
                }
            }

            $stepStatus = $status['result'];
            $response['steps'][] = ['step' => $step, 'status' => $status];

            if ($status['result'] === 'Failed' || $status['result'] === 'Pending' || $status['result'] === 'Skipped') {
                $response['status'] = $status['result'];

                if ($noFailure) {
                    $noFailure = false;
                    if ($status['result'] === 'Failed') {
                        if (isset($status['exception']) && $status['exception'] instanceof IncompleteTest) {
                            $response['status'] = 'Pending';
                        } elseif (isset($status['exception']) && $status['exception'] instanceof SkippedTest) {
                            $response['status'] = 'Skipped';
                        }
                        $response['exception'] = $status['exception'] ?? null;
                        $status = ['result' => 'Abort', 'asserts' => 0, 'exception' => null];
                    } elseif ($status['result'] === 'Skipped') {
                        $response['exception'] = $status['exception'] ?? null;
                        $status = ['result' => 'Abort', 'asserts' => 0, 'exception' => null];
                    } else {
                        $incoming = $status['exception'] ?? null;
                        $status = ['result' => 'Pending', 'asserts' => 0, 'exception' => null];
                        $response['exception'] = $incoming ?? new IncompleteTestError("Step Not Found: $step");
                    }
                }
            }

            foreach ($tokens as $token) {
                $response['multi-steps']['status'][$token] = $stepStatus;
            }
        }

        if ($response['status'] === null) {
            $response['status'] = $status['result'] === 'Passed' ? 'Passed' : $status['result'];
        }

        return $response;
    }
}
