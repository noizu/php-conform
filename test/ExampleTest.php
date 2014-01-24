<?php
use NoizuLabs\PHPConform\PHPUnitExtension as PhpConform;

require_once(dirname(__FILE__) . '/ExampleSteps.php');
require_once(dirname(__FILE__) . '/StepsForExample.php');
require_once(dirname(__FILE__) . '/StepsForScenario.php');
define("TRACE_AUTOLOAD",true);
/**
 * @stepClass ExampleSteps
 * @stepClass StepsForExample
 */
class ExampleBeheviourDrivenDesignTestSuiteTest extends PhpConform\ScenarioSuite
{
    public $setupCalled; 
    public $tearDownCalled; 

    /**
     * @sicenario
     * @stepClass StepsForScenario
     */
    public function stepFunctions()
    {

    }

    /**
     * @iscenario
     * @stepClass ExampleSteps2
     */
    public function hereIsExampleTwo()
    {

    }

    /** @iscenario */
    public function hereIsExampleThree()
    {

    }

    /**
     * @then step functions from the test suite class should be callable
     */
    public function StepClassStep4()
    {

    }

    public function setUp()
    {
         $this->setupCalled++; 
    } 

    public function tearDown()
    {
         $this->tearDownCalled++;
         if($this->tearDownCalled > 1) throw new Exception("test"); 
    }


    /**
     * @then the setup method should be called 
     */
    public function SetupShouldHaveBeenCalled()
    {
	$this->assertTrue($this->setupCalled > 0, "setupCalled value was not set"); 
    }


    /**
     * @then the teardown method should be called 
     */
    public function TearDownShouldHaveBeenCalled()
    {
        $this->assertTrue($this->tearDownCalled > 0, "tearDownCalled value was not set"); 
    }


}