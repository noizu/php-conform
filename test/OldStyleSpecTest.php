<?php

require_once 'PHPUnit/Extensions/Story/TestCase.php';

class OldStyleSpec extends PHPUNit_Extensions_Story_TestCase
{

    /**
     * @scenario
     */
    public function PHPConformPlaysNicelyWithLegacyPHPUnitBDDScenarios()
    {

        $this->given("PHPConform makes interesting use of the Reflection API")->
                when("a user uses the phpunit --story flag")->
                and("there are a mix of phpConform and legacy BDD tests")->
                then("PHPConform output will be used for new tests and legacy story output will be used for old tests");
    }

    public function runGiven(&$world, $action, $arguments)
    {

    }

    public function runWhen(&$world, $action, $arguments)
    {

    }

    public function runThen(&$world, $action, $arguments)
    {
        if ($action == "I go to bed") {
            $this->fail("No I shall never go to bed");
        }
    }

}

