<?php
use NoizuLabs\PHPConform\PHPUnitExtension as PhpConform;
require_once(dirname(__FILE__) . '/sut/Calculator.php');

class StepsForExample extends PhpConform\StepAbstract
{

    /**
     * @then step functions included by the class should be callable
     */
    public function StepFunctionIncludedForClass()
    {
       $this->outsideVar = true;
    }
}
