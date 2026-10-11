<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests;

use NoizuLabs\PHPConform\PHPUnitExtension\ScenarioSuite;
use NoizuLabs\PHPConform\PHPUnitExtension\StepAbstract;
use NoizuLabs\PHPConform\PHPUnitExtension\StepRunner;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\IncompleteTestError;
use PHPUnit\Framework\TestCase;

class ConformInternalsTest extends TestCase
{
    private StepFixture $steps;

    protected function setUp(): void
    {
        $this->steps = new StepFixture();
    }

    public function testParsesStoryScenarioAndSteps(): void
    {
        $parsed = ScenarioSuite::parseScenarioSuite(
            "#Story: Parser Check\nAs a dev\nI want parsing\n\n#Scenario: Simple\nGiven a php conform test\nWhen running phpunit\nThen all is well\n",
            'parser.suite'
        );

        $this->assertSame('parserCheck', $parsed['storyName']);
        $this->assertSame(['As a dev', 'I want parsing'], $parsed['story']);
        $this->assertSame(
            ['Given a php conform test', 'When running phpunit', 'Then all is well'],
            $parsed['scenarios']['Simple']
        );
        $this->assertSame([], $parsed['examples']);
    }

    public function testParsesExampleTablesIntoColumns(): void
    {
        $parsed = ScenarioSuite::parseScenarioSuite(
            "#Story: T\n\n#Scenario: Data Driven\nGiven a calculator\nWhen I add <a> plus <b>\nThen the total should be <c>\n\nExamples:\n    | a | b | c |\n    | 1 | 2 | 3 |\n    | 4 | 5 | 9 |\n",
            'examples.suite'
        );

        $examples = $parsed['examples']['Data Driven'];
        $this->assertSame(['a' => ['1', '4'], 'b' => ['2', '5'], 'c' => ['3', '9']], $examples);
    }

    public function testExactSentenceStepMatches(): void
    {
        $result = $this->steps->executeStep('Given a php conform test');
        $this->assertSame('Passed', $result['result']);
        $this->assertSame(1, $result['asserts']);
    }

    public function testUnmatchedStepIsPending(): void
    {
        $result = $this->steps->executeStep('Then nobody defined this step');
        $this->assertSame('Pending', $result['result']);
    }

    public function testAndStepsInheritLastAction(): void
    {
        $result = $this->steps->executeStep('Given a calculator');
        $this->assertSame('Passed', $result['result']);

        // "And I ..." is rewritten to "Given I ..." before matching.
        $result = $this->steps->executeStep('And I also want a calculator');
        $this->assertSame('Passed', $result['result']);
    }

    public function testDollarSignTokenBindsMethodArguments(): void
    {
        $result = $this->steps->executeStep('Given the value is 42');
        $this->assertSame('Passed', $result['result']);
        $this->assertSame(42, $this->steps->boundValue);
    }

    public function testPregmatchCaptureBindsMatches(): void
    {
        $result = $this->steps->executeStep('Given the regex value is 99 red balloons');
        $this->assertSame('Passed', $result['result']);
        $this->assertSame('99', $this->steps->regexValue);
    }

    public function testFailedAssertionMarksStepFailed(): void
    {
        $result = $this->steps->executeStep('Then this step always fails');
        $this->assertSame('Failed', $result['result']);
        $this->assertInstanceOf(ExpectationFailedException::class, $result['exception']);
    }

    public function testIncompleteStepReportsPending(): void
    {
        $result = $this->steps->executeStep('Then this step is not done yet');
        $this->assertSame('Pending', $result['result']);
        $this->assertInstanceOf(IncompleteTestError::class, $result['exception']);
    }

    public function testStepRunnerChainsContainedModules(): void
    {
        $runner = new StepRunner(null, new ContainedStepFixture());
        $result = $runner->executeStep('Then a contained step module ran');
        $this->assertSame('Passed', $result['result']);
    }

    public function testAttributeDeclaredStepsMatch(): void
    {
        $result = (new AttributeStepFixture())->executeStep('Given an attribute declared step');
        $this->assertSame('Passed', $result['result']);
    }

    public function testSingleLineDocblockRegistersStepSentence(): void
    {
        $result = (new SingleLineDocblockSteps())->executeStep('Given a calculator');
        $this->assertSame('Passed', $result['result']);
        $this->assertSame(1, $result['asserts']);
    }
}

class StepFixture extends StepAbstract
{
    public mixed $boundValue = null;
    public mixed $regexValue = null;

    /**
     * @given a php conform test
     */
    public function SetupPHPConformTest()
    {
        $this->assertTrue(true);
    }

    /**
     * @given a calculator
     */
    public function createCalculator()
    {
        $this->assertTrue(true);
    }

    /**
     * @given I also want a calculator
     */
    public function anotherCalculator()
    {
        $this->assertTrue(true);
    }

    /**
     * @given the value is $value
     */
    public function bindValue($value)
    {
        $this->boundValue = intval($value);
        $this->assertTrue(true);
    }

    /**
     * @pregmatch #
     * @given the regex value is (.*) red balloons
     */
    public function bindRegex($matches)
    {
        $this->regexValue = $matches[1];
        $this->assertTrue(true);
    }

    /**
     * @then this step always fails
     */
    public function failingStep()
    {
        $this->assertTrue(false, 'deliberate failure');
    }

    /**
     * @then this step is not done yet
     */
    public function incompleteStep()
    {
        $this->markTestIncomplete('still to be implemented');
    }
}

class ContainedStepFixture extends StepAbstract
{
    /**
     * @then a contained step module ran
     */
    public function containedStep()
    {
        $this->assertTrue(true);
    }
}

class AttributeStepFixture extends StepAbstract
{
    #[\NoizuLabs\PHPConform\PHPUnitExtension\Attributes\Given('an attribute declared step')]
    public function attributeStep()
    {
        $this->assertTrue(true);
    }
}

class SingleLineDocblockSteps extends StepAbstract
{
    /** @given a calculator */
    public function createCalculator()
    {
        $this->assertTrue(true);
    }
}
