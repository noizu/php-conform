<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests;

use NoizuLabs\PHPConform\PHPUnitExtension\ScenarioSuite;

/**
 * @stepClass ExampleSteps
 * @stepClass StepsForExample
 */
class ExampleBehaviourDrivenDesignTestSuiteTest extends ScenarioSuite
{
    public int $setupCalled = 0;
    public int $tearDownCalled = 0;
    public static int $tearDownRuns = 0;

    /**
     * Declared (rather than relying on ScenarioSuite's
     * #[\AllowDynamicProperties]) so the Selenium session is a first-class
     * property. $webdriver is already declared on ScenarioSuite itself.
     */
    public mixed $session = null;

    /**
     * @given a selenium test
     */
    public function GivenASeleniumTest()
    {
        try {
            $this->webdriver = $this->getWebDriver();
        } catch (\Exception $e) {
            $this->markTestSkipped('Selenium support is not installed/configured: ' . $e->getMessage());
        }

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
        $this->assertEquals('http://www.google.com/', $this->session->url());
    }

    /**
     * @stepClass StepsForScenario
     */
    public function stepFunctions()
    {
    }

    /**
     * @then step functions from the test suite class should be callable
     */
    public function StepClassStep4()
    {
    }

    /**
     * @then values set by outside step classes should be available
     */
    public function outsideVarsShouldBeAvailable()
    {
        $this->assertTrue($this->outsideVar, 'external steps did not correctly set my property');
    }

    /**
     * @then the setup method should be called
     */
    public function SetupShouldHaveBeenCalled()
    {
        $this->assertTrue($this->setupCalled > 0, 'setupCalled value was not set');
    }

    /**
     * @then the teardown method should be called
     */
    public function TearDownShouldHaveBeenCalled()
    {
        // Each scenario is its own PHPUnit data set; tearDown has already
        // run for every earlier scenario in this process by now.
        $this->assertTrue(self::$tearDownRuns > 0, 'tearDown was never invoked');
    }

    protected function setUp(): void
    {
        $this->setupCalled++;
    }

    protected function tearDown(): void
    {
        $this->tearDownCalled++;
        self::$tearDownRuns++;
    }
}
