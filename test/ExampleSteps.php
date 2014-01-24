<?php

use NoizuLabs\PHPConform\PHPUnitExtension as PhpConform;

require_once(dirname(__FILE__) . '/sut/Calculator.php');

class ExampleSteps extends PhpConform\StepAbstract
{

    /**
     * @given a php conform test
     */
    public function SetupPHPConformTest()
    {

    }

    /**
     * @when running phpunit
     */
    public function RunningPHPUnit()
    {

    }

    /**
     * @given hello world
     */
    public function givenHelloWorld()
    {
        $this->assertTrue(true);
    }

    /**
     * @pregmatch #
     * @when we call a step function that uses a regular expression syntax to match and store the value of (.*)
     */
    public function storeVariableRetrievedUsingRegex($matches)
    {
        $this->inputValue = intval($matches[1]);
    }

    /**
     * @when we call a step function that uses the dollar sign notation to match and store the value of $var
     */
    public function storeVariabeRetrievedUsingDollarSignNotation($var)
    {
        $this->inputValue = intval($var);
    }

    /**
     * @then the step function should have recieved the specified param value of 7
     */
    public function verifyStoredValueOf7()
    {
        $this->assertEquals(7, $this->inputValue);
    }

    /**
     * @then the step function should have recieved the specified param value of 5
     */
    public function verifyStoredValueof5()
    {
        $this->assertEquals(5, $this->inputValue);
    }

    /**
     * @given a calculator
     */
    public function createCalculator()
    {
        $this->_calculator = new Calculator();
    }

    /**
     * @when I add $arg1 plus $arg2
     */
    public function Addition($arg1, $arg2)
    {
        $this->_calculator->add($arg1, $arg2);
    }

    /**
     * @when I multiply $arg1 by $arg2
     */
    public function Multiplication($arg1, $arg2)
    {
        $this->_calculator->multiply($arg1, $arg2);
    }

    /**
     * @then the total should be $arg
     */
    public function sampleStep5($arg)
    {
        $this->assertTrue(true);
        $this->assertEquals(intval($arg), $this->_calculator->equals());
    }

}
