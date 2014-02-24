<?php
namespace NoizuLabs\PHPConform\PHPUnitExtension;

/** @todo - Restore functionality from phpunit 3.4 utils class I am disabling here*/
DEFINE("ENABLE_UTIL", false);

require_once 'PHPUnit/Autoload.php';

/**
 *  The ScenarioSuite class provides the phphook phpunit needs to access a BDD.suite file.
 *  We use some reflection to muscle our way into the phpunit engine and override a few default behaviors.
 */
abstract class ScenarioSuite extends \PHPUnit_Framework_TestCase
{
	protected $container; 
	protected $webdriver = null;
    public $Story;
    public $Scenario;
    public function toString()
    {
        return "Story:" . trim($this->Story) . " \"" . trim($this->Scenario) . "\"";
    }

    protected $_story = array();
    protected $_storyName = false;
    protected $_scenarios = array();
    protected $_stepRunner;
    protected $_examples;
    protected $_tests;
    protected $_stockStoryPrinter = null;
    public $_scenario;

    public function &getReference($arg)
    {
        if(property_exists($this,$arg)) {

        } else {
            $this->arg = null;
        }

        return $this->$arg;
    }

    public function testPHPConformTestSuite()
    {

    }

	public function getWebDriver($alternativeHost = null)
	{
		if($this->webdriver == null) {
			if(isset($this->container['WebDriver'])) {
                                $host = is_null($alternativeHost) ? $this->container['SeleniumHost'] : $alternativeHost; 
				$this->webdriver = $this->container['WebDriver']($host);
			}
		}
		
		if(is_null($this->webdriver)) {
			throw new \Exception("WebDriver not available from \$this->container['WebDriver']");
		}
		return($this->webdriver); 
	}
	
    public function __construct($theClass = '', $name = '', $pimple = null)
    {
		if($pimple == null)
		{		
			global $noizulabs_phpconform_container;
			if(isset($noizulabs_phpconform_container))
			{			
				$this->container = $noizulabs_phpconform_container;
			} else {
				$this->container = new \Pimple\Pimple();
			}
		}
        $this->loadScenarioSuite();
        $this->loadSteps();
        $flag = false;
    }

    public function &register(Module &$mod, $directWrite = false)
    {
        $mod->register($this, $directWrite);
        return $mod;
    }

    public function count()
    {
        return count($this->_scenarios);
    }

    public function getSuite()
    {
        return $this->_storyName;
    }

    protected function loadSteps()
    {
        // Load Global Steps (Modules & inclass)
        $t = $this->getAnnotations();

        if(isset($t['class']['stepClass'])) {
        	$stepClasses = $t['class']['stepClass'];
        }

        if (!empty($stepClasses)) {
            $this->_stepRunner = $this->register(new StepRunner());
            foreach ($stepClasses as $class) {
                $runner = $this->register(new $class(), true);
                $this->_stepRunner->addStepModule($runner);
            }
        } else {
            $this->_stepRunner = null;
        }
    }

    protected function loadScenarioSteps($name)
    {
        $method = $this->sentenceToCamelCase($name);
        $stepRunner = null;

        if (method_exists($this, $method)) {
            // Load Global Steps (Modules & inclass)
            $t = $this->getAnnotations($method);
            $stepClasses = $t['method']['stepClass'];
            if (!empty($stepClasses)) {
                $stepRunner = $this->register(new StepRunner());
                foreach ($stepClasses as $class) {
                    $runner = $this->register(new $class(), true);
                    $stepRunner->addStepModule($runner);
                }
            }
        }
        // Load Steps for a specific Scenario
        return $stepRunner;
    }

    protected function loadScenarioSuite()
    {
        $class = get_class($this);
        $reflection = new \ReflectionClass($class);
        $scenarioDir = dirname($reflection->getFileName());

        if (isset($this->scenarioFile)) {
            $file = $this->scenarioFile;
        } else {
            $class = preg_replace('/Test$/', '', $class);
            $file = "";
            $class = (strpos($class, "\\") !== false) ? end(explode("\\", $class)) : $class;
            $file .= $class{0};
            for ($i = 1; $i < strlen($class); $i++) {
                if (ctype_upper($class{$i}) && !ctype_upper($class{$i - 1})) {
                    $file .= '_';
                } else if ($i > 1 &&
                           !ctype_upper($class{$i}) &&
                           ctype_upper($class{$i - 1}) &&
                           ctype_upper($class{$i - 2})) {
                    $file = substr($file, 0, strlen($file) - 1);
                    $file.='_';
                    $file.=$class{$i - 1};
                }
                $file .= $class{$i};
            }
            $file = strtolower($file);
        }

        $full = $scenarioDir . "/" . $file . ".suite";
        $contents = file_get_contents($full);
        $this->parseScenarioSuite($full, $contents);
    }

    protected function sentenceToCamelCase($sentence)
    {
        $sentence = trim($sentence);
        $sentence = preg_replace("/\s\s?/", " ", $sentence);
        $sentence = explode(" ", $sentence);
        $cs = "";
        foreach ($sentence as $word) {
            $cs .= ucfirst($word);
        }
        $cs = lcfirst($cs);
        return $cs;
    }

    /**
     * @todo Cleanup ParseLogic. Break into smaller more maintainble chunks.
     */
    protected function parseScenarioSuite($fullFile, $contents)
    {
        $contents = explode("\n", $contents);
        $mode = null;
        $lastCommand = null;
        $story = false;
        $match = array();
        $pointer = false;
        $emptyState = false;
        foreach ($contents as $line) {
            $line = trim($line);
            if (preg_match("/#Scenario:(.*)/", $line, $matches)) {
                $mode = "Scenario";
                $lastCommand = null;
                //$scenario = $this->sentenceToCamelCase($matches[1]);
                $scenario = $matches[1];
                if (!empty($this->_scenarios[$scenario])) {
                    print("Duplicate Test Case Found ($scenario)\nLine $cursor in $fullFile\n\n");
                    trigger_error("duplicate scenario definition for ($scenario) in $fullFile\n", E_USER_WARNING);
                    $pointer = & $emptyState;
                } else {
                    $this->_scenarios[$scenario] = array();
                    $pointer = & $this->_scenarios[$scenario];
                }
            } else if (preg_match("/#Story:(.*)/", $line, $matches)) {
                $mode = "Story";
                $lastCommand = null;
                $this->_storyName = $this->sentenceToCamelCase($matches[1]);
                if (!empty($this->_story)) {
                    print("multiple Story definitions found in $fullFile\n"); 
                    trigger_error("multiple Story definitions found in $fullFile\n", E_USER_WARNING);
                    $pointer = & $emptyState;
                } else {
                    $this->_story = array();
                    $pointer = &$this->_story;
                }
            } else if (preg_match("/Examples:/", $line)) {
                $mode = "Example";
                $lastCommand = null;
                $headerLine = false;
                $this->_examples[$scenario] = array();
                $pointer = & $this->_examples[$scenario];
            } else {

                if ($mode == "Example") {
                    if ($headerLine) {
                        if (!empty($line)) {
                            $values = explode("|", $line);
                            foreach ($headerLine as $k => $v) {
                                $pointer[$v][] = trim(isset($values[$k + 1]) ? $values[$k + 1] : "");
                            }
                        }
                    } else {
                        $line = trim($line);
                        if (!empty($line)) {
                            $headerLine = explode("|", $line);
                            array_shift($headerLine);
                            array_pop($headerLine);
                            foreach ($headerLine as $k => $v) {
                                $headerLine[$k] = trim($v);
                            }

                            foreach ($headerLine as $k => $v) {
                                $pointer[$v] = array();
                            }
                        }
                    }
                } else {
                    if ($pointer !== false && strlen($line)) {
                        if (strpos($line, "#") !== 0 && strpos($line, "--") !== 0) {
                            if ($mode == "Scenario") {
                                if (strpos(strtolower($line), "given") === 0) {
                                    $lastCommand = "Given";
                                } else if (strpos(strtolower($line), "when") === 0) {
                                    $lastCommand = "When";
                                } else if (strpos(strtolower($line), "Then") === 0) {
                                    $lastCommand = "Then";
                                } else if (strpos(strtolower($line), "and") === 0 ||
                                           strpos(strtolower($line), "but") === 0) {
                                    if (is_null($lastCommand)) {
                                        trigger_error("And must proceed Given,When or Then ($line)", E_USER_ERROR);
                                    } else {

                                    }
                                }
                            }
                            $pointer[] = $line;
                        }
                    }
                }
            }
        }
    }

    public function getNumAssertions()
    {
        return $this->numAsserts;
    }

    public function replaceStoryListener($result)
    {
        $r = new \ReflectionObject($result);
        $p = $r->getProperty("listeners");
        $p->setAccessible(true);
        $listeners = $p->getValue($result);
        foreach ($listeners as &$listener) {
        	//PHPUnit_Extensions_Story_ResultPrinter_Text

            if ($listener instanceof \PHPUnit_Util_TestDox_ResultPrinter_Text) {
                $this->_stockStoryPrinter = $listener;
                $t = new BDDPrinter();
                $t->register($this->_stockStoryPrinter);
                $listener = $t;
            } else {

            }
        }
        $p->setValue($result, $listeners);
    }

    public function restoreStoryListener($result)
    {
        if (isset($this->_stockStoryPrinter)) {
            $r = new \ReflectionObject($result);
            $p = $r->getProperty("listeners");
            $p->setAccessible(true);
            $listeners = $p->getValue($result);
            foreach ($listeners as &$listener) {
                if ($listener instanceof BDDPrinter) {
                    $listener = $listener->getOwner();
                }
            }
            $p->setValue($result, $listeners);
        }
    }

    public function run( \PHPUnit_Framework_TestResult $result = NULL )
    {
        static $ran = array();

        if (empty($ran[$this->_storyName])) {
            $ran[$this->_storyName] = true;
            if ($result == NULL) {
                $result = $this->createResult();
            }
            $this->replaceStoryListener($result);
            foreach ($this->_scenarios as $key => $scenario) {
                $this->runSinglePass($key, $scenario, $result);
            }
            $this->restoreStoryListener($result);
            return $result;
        }
    }

    protected function runSinglePass($name, $scenario, &$result, $example = null)
    {
        $this->name = $name;
        $steps = $scenario;
        if (!empty($this->_examples) && array_key_exists($name, $this->_examples)) {
            $examples = $this->_examples[$name];
        } else {
            $examples = array();
        }

        $additionalSteps = $this->loadScenarioSteps($name);

        $stepRunner = new StepRunner($additionalSteps, $this->_stepRunner, $this);
        $test = new Scenario($this->_storyName, $name, $steps, $stepRunner);

        $c = clone $this;
        $c->Story = $this->_storyName;
        $c->Scenario = $name;
        $result->startTest($c);
        if(ENABLE_UTIL) \PHPUnit_Util_Timer::start();
        try {
             // setup
             if(method_exists($this, "setUp")) {
                  $this->setUp();
             }

             $test->executeScenario($result, $examples);

             // tearDown
             if(method_exists($this, "tearDown")) {
                  $this->tearDown();
             }
        } catch(\Exception $e) {
             $result->currentScenario['status'] = "Exception";
             $result->currentScenario['exception'] = $e;
        }


        $c->currentScenario = $result->currentScenario;
        $c->numAsserts += $result->currentScenario['asserts'];
        if(ENABLE_UTIL)
        {
        	$current = \PHPUnit_Util_Timer::current();
        } else {
        	$current = 0;
        }

        switch ($result->currentScenario['status']) {
            case 'Passed':
                // Hurrah
                break;

            case 'Skipped':
            case 'Incomplete':
            case 'Failed':
                $result->addFailure(
                    $c, $result->currentScenario['exception'],
                    $current
                );
                break;

            case 'Exception':
                $result->addError(
                    $c,
                    $result->currentScenario['exception'],
                    $current
                );
                break;

            case 'Pending':
                $result->addError(
                    $c,
                    (isset($result->currentScenario['exception']) ?
                            $result->currentScenario['exception'] :
                            new \PHPUnit_Framework_IncompleteTestError("Test Not Implemented")),
                    		$current
                );
                break;
            default:
                $result->addError(
                    $c,
                    (isset($result->currentScenario['exception']) ?
                            $result->currentScenario['exception'] :
                            new \Exception("Unknown Exception Occurred")),
                            $current
                );
                // new PHPUnit_Framework_AssertionFailedError("Unknown Exception Occurred"))
                break;
        }

        if(ENABLE_UTIL)
        {
        	$stop = \PHPUnit_Util_Timer::current();
        } else {
        	$stop = 0;
        }
        $result->endTest($c, $stop);
    }

    protected function convertResult($standard)
    {
        $result = new \TestResult;
        $result->convert($standard);
        return $result;
    }

    public function getAnnotations($method = '')
    {
        return \PHPUnit_Util_Test::parseTestMethodAnnotations(
            get_class($this), $method
        );
    }

}

