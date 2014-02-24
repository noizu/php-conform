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
	* @given a selenium test
	*/
	public function GivenASeleniumTest()
	{
		$this->webdriver = $this->getWebDriver(); 

		$this->session = $this->webdriver->session('firefox'); 
	}

	/**
	* @when a user navigates to google
	*/
	public function WhenAUserNavigatesToGoogle()
	{
	
	        $this->session->open('http://www.google.com/ncr');
	}
	 
	/**
	* @then the active session page should be google
	*/
	public function ThenTheActiveSessionPageShouldBeGoogle()
	{
		$this->assertEquals("http://www.google.com/", $this->session->url());
	}
	
	
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
    * @then  values set by outside step classes should be available
    */
    public function testOutsideVars()
    {
       $this->assertTrue($this->outsideVar, "external steps did not correctly set my property");
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
