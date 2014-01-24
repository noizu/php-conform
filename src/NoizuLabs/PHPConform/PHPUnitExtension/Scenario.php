<?php
namespace NoizuLabs\PHPConform\PHPUnitExtension;

if(ENABLE_UTIL)  \PHPUnit_Util_Filter::addFileToFilter(__FILE__, 'PHPUNIT');
require_once(dirname(__FILE__) . '/Module.php');

final class Scenario extends Module
{
    protected $_name = false;
    protected $_steps = false;
    protected $_stepRunner = false;
    protected $_suite = false;

    public function getName()
    {
        return "hmm";
    }

    public function __construct($suite, $name, array $steps, StepAbstract $stepRunner)
    {
        $this->_suite = $suite;
        $this->_name = $name;
        $this->_steps = $steps;
        $this->_stepRunner = $stepRunner;
        $this->_stepRunner->setScenario($this->_name);
    }

    public function getSuite()
    {
        return $this->_suite;
    }

    public function executeScenario(&$ref, $examples)
    {
        $ref->currentScenario = array();
        $ref->currentScenario['steps'] = array();
        $ref->currentScenario['status'] = "Pending";
        $ref->currentScenario['asserts'] = 0;

        if (empty($examples)) {
            $resp = $this->execute($this->_steps);
            $ref->currentScenario['asserts'] += intval($resp['asserts']);
            $ref->currentScenario['status'] = $resp['status'];
            $ref->currentScenario['exception'] = $resp['exception'];
            $ref->currentScenario['steps'] = $resp['steps'];
        } else {
            $ref->currentScenario['multi'] = true;
            $ref->currentScenario['multi-steps'] = array();
            $ref->currentScenario['examples'] = $examples;
            $replacements = array_keys($examples);
            $count = count($examples[$replacements[0]]);

            $raw = $this->_steps;

            $continue = true;
            for ($i = 0; $i < $count && $continue; $i++) {
                $parsedSteps = array();
                $tokens = array();


                $rv = array();
                foreach ($replacements as $token) {
                    $rv[$token] = $examples[$token][$i];
                }
                $resp = $this->execute($raw, $rv);


                /** Aggregation logic */
                $ref->currentScenario['asserts'] += intval($resp['asserts']);


                $ref->currentScenario['multi-steps']['status'][$i] = $resp['multi-steps']['status'];
                $ref->currentScenario['multi-steps']['exception'][$i] = $resp['exception'];
            }

            foreach ($raw as $v) {
                $ref->currentScenario['steps'][] = array('step' => $v, 'status' => array('result' => 'Multi'));
            }
        }
    }

    protected function execute($steps, $replacements = array())
    {
        $noFailure = true;
        $failure = null;
        $response = array('asserts' => 0, 'result' => null, 'steps' => array(), 'exception' => null);


        foreach ($replacements as $token => $value) {
            $response['multi-steps']['status'][$token] = "Pending";
        }

        foreach ($steps as $step) {
            $tokens = array();

            foreach ($replacements as $token => $value) {
                if (strpos($step, "<$token>") !== false) {
                    $tokens[] = $token;
                }
                $step = str_replace("<$token>", $value, $step);
            }

            if ($noFailure) {
                $status = $this->_stepRunner->executeStep($step);
                $response['asserts'] += $status['asserts'];
            }

            $response['steps'][] = array('step' => $step, 'status' => $status);
            $stepStatus = $status['result'];

            if ($status['result'] == "Failed" || $status['result'] == "Pending") {
                $response['status'] = $status['result'];

                if ($noFailure) {
                    $noFailure = false;
                    if ($status['result'] == "Failed") {
                        if ($status['exception'] instanceof \PHPUnit_Framework_IncompleteTest) {
                            $response['status'] = "Pending";
                        } else if ($status['exception'] instanceof \PHPUnit_Framework_SkippedTest) {
                            $response['status'] = "Skipped";
                        }
                        $response['exception'] = $status['exception'];
                        $status = array('result' => 'Abort', 'asserts' => 0, 'exception' => null);
                    } else {
                        $status = array('result' => 'Pending', 'asserts' => 0, 'exception' => null);
                        $response['exception'] = new \PHPUnit_Framework_IncompleteTestError("Step Not Found: $step");
                    }
                }
            }

            foreach ($tokens as $token) {
                $response['multi-steps']['status'][$token] = $stepStatus;
            }
        }

        if ($status['result'] == "Passed") {
            $response['status'] = "Passed";
        }

        return $response;
    }

    public function runTest()
    {
        try {
            $response = $this->executeScenario();
        } catch (\Exception $e) {
            throw $e;
        }
        return $response;
    }

    public static function parseTestMethodAnnotations($className, $methodName = '')
    {
        return array(
            'class' => array(),
            'method' => array(),
        );
    }

    public function getAnnotations()
    {
    }
}
