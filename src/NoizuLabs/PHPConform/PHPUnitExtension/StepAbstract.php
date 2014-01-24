<?php
namespace NoizuLabs\PHPConform\PHPUnitExtension;

abstract class StepAbstract extends Module
{

    protected $_containedSteps = array();
    protected $_regexMap = array();
    protected $_failed = false;
    protected $_failure = null;
    protected $_scenario = "";
    protected $_lastAction = null;

    public function __construct()
    {
        $this->registerSteps();
    }

    public function setScenario($scenario)
    {
        $this->_scenario = $scenario;
        foreach ($this->_containedSteps as $key => $value) {
            $this->_containedSteps[$key]->setScenario($scenario);
        }
    }

    public function setUp()
    {
        $this->_lastAction = null;
    }

    public function tearDown()
    {

    }

    public function executeStep($stepString)
    {
        if (strpos(strtolower($stepString), "given") === 0) {
            $this->_lastAction = "Given";
        } else if (strpos(strtolower($stepString), "when") === 0) {
            $this->_lastAction = "When";
        } else if (strpos(strtolower($stepString), "then") === 0) {
            $this->_lastAction = "Then";
        } else if (strpos(strtolower($stepString), "and") === 0 || strpos(strtolower($stepString), "but") === 0) {
            if (is_null($this->_lastAction)) {
                trigger_error("And or But may only be used after Given, When or Then", E_USER_ERROR);
            } else {
                $stepString = $this->_lastAction . substr($stepString, 3);
            }
        }

        $result = $this->executeLocalSteps($stepString);
        if ($result['result'] !== 'Pending') {
            return $result;
        }
        $result = $this->executeContainedSteps($stepString);
        return $result;
    }

    public function addStepModule(StepAbstract $stepSuite)
    {
        $this->_containedSteps[] = $stepSuite;
    }

    protected function executeLocalSteps($stepString)
    {
        foreach ($this->_regexMap as $step) {
            if ($step['regex']) {
                $matches = array();
                if (preg_match($step['string'], $stepString, $matches)) {
                    try {
                        if (isset($step['varmap'])) {
                            $args = array();
                            $i = 1;
                            foreach ($step['varmap'] as $key => $value) {
                                $args[] = $matches[$i];
                                $i++;
                            }
                            call_user_func_array(array($step['class'], $step['function']), $args);
                        } else {
                            call_user_func_array(
                                array($step['class'],
                                $step['function']),
                                array("matches" => $matches)
                            );
                        }
                        $asserts = $this->getCount();
                        $this->resetCount();
                        $response = array('result' => 'Passed', 'asserts' => $asserts, 'exception' => null);
                    } catch (\PHPUnit_Framework_AssertionFailedError $e) {
                        $asserts = $this->getCount();
                        $this->resetCount();

                        $this->_failed = true;
                        $this->_failure = $e;
                        $response = array('result' => 'Failed', 'asserts' => $asserts, 'exception' => $e);
                    } catch (\Exception $e) {
                        $asserts = $this->getCount();
                        $this->resetCount();
                        $this->_failed = true;
                        $this->_failure = $e;
                        $response = array('result' => 'Exception', 'asserts' => $asserts, 'exception' => $e);
                    }
                    return $response;
                }
            } else {
                if ($step['string'] == $stepString) {
                    try {
                        call_user_func_array(array($step['class'], $step['function']), array());
                        $asserts = $this->getCount();
                        $this->resetCount();


                        $response = "Passed";
                        $response = array('result' => 'Passed', 'asserts' => $asserts, 'exception' => null);
                    } catch (\PHPUnit_Framework_AssertionFailedError $e) {
                        $asserts = $this->getCount();
                        $this->resetCount();

                        $this->_failed = true;
                        $this->_failure = $e;
                        $response = array('result' => 'Failed', 'asserts' => $asserts, 'exception' => $e);
                    } catch (\Exception $e) {
                        $asserts = $this->getCount();
                        $this->resetCount();
                        $this->_failed = true;
                        $this->_failure = $e;
                        $response = array('result' => 'Exception', 'asserts' => $asserts, 'exception' => $e);
                    }

                    return $response;
                }
            }
        }

        $asserts = $this->getCount();
        $this->resetCount();
        return array('result' => 'Pending', 'asserts' => $asserts, 'exception' => null);
    }

    protected function executeContainedSteps($stepString)
    {
        if (is_array($this->_containedSteps)) {
            foreach ($this->_containedSteps as $stepRunner) {
                $response = $stepRunner->executeStep($stepString);
                if ($response['result'] !== 'Pending') {
                    return $response;
                }
            }
        }
        $asserts = $this->getCount();
        $this->resetCount();
        return array('result' => 'Pending', 'asserts' => $asserts, 'exception' => null);
    }

    public function registerSteps()
    {
        $this->registerClassSteps($this);
    }

    public function registerClassSteps($targetClass)
    {
        $reflection = new \ReflectionClass(get_class($targetClass));
        $methods = $reflection->getMethods();
        $matches = array();
        $steps = array();

        foreach ($methods as $method) {
            $doc = $method->getDocComment();
            $regexp = false;
            $delim = "/";
            $varmap = null;

            if (preg_match("/@pregmatch (.)/", $doc, $matches)) {
                $regexp = true;
                if (isset($matches[1])) {
                    $delim = $matches[1];
                } else {
                    $delim = "/";
                }
            }

            $sentence = "";

            if (preg_match("/@([tT]hen|[wW]hen|[gG]iven) (.*)/", $doc, $matches)) {
                $sentence = ucfirst($matches[1]) . " " . trim($matches[2]);
            }

            if (strlen($sentence) === 0) {
                $function = $method->getShortName();
                if (preg_match("/^([tT]hen|[wW]hen|[gG]iven) (.*)/", $function, $matches)) {
                    $sentence = ucfirst($matches[1]) . trim($matches[2]);
                }
            }

            // if user has not specified regexp mode then look for dollar signs in sentence,
            // if found pop names on params array
            if (strlen($sentence)) {

                if (!$regexp) {
                    if (preg_match_all("/(\\s\\\$[^\\s]*)/", $sentence, $matches)) {
                        $regexp = true;
                        array_shift($matches);
                        $varmap = $matches[0];
                        $sentence = preg_replace("/(\\s\\\$[^\\s]*)/", " ([^\s]*)", $sentence);
                    }
                }

                if ($regexp) {
                    $sentence = $delim . $sentence . $delim;
                }
                $this->_regexMap[] = array(
                    'regex' => $regexp,
                    'string' => $sentence,
                    'varmap' => $varmap,
                    'class' => $targetClass,
                    'function' => $method->getShortName(),
                );
            }
        }
    }
}
