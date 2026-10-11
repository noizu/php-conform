<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests;

use NoizuLabs\PHPConform\PHPUnitExtension\BDDPrinter;
use NoizuLabs\PHPConform\PHPUnitExtension\BDDSubscriber;
use NoizuLabs\PHPConform\Tests\Rendering\RenderingCoverageTest;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\IncompleteTestError;
use PHPUnit\Framework\SkippedTest;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end coverage of the story rendering: runs the rendering-coverage
 * fixture scenarios (deliberately failing/pending/skipped/multi, excluded
 * from this suite's own run) through ScenarioSuite::testScenario(), then
 * renders the recorded outcome via BDDSubscriber::renderScenario() — the
 * exact path notify() drives from PHPUnit's Finished events — with the
 * printer writing to a capture stream, and asserts every documented mark.
 */
class ScenarioRenderingOutputTest extends TestCase
{
    public function testFailingScenarioRendersFailureAndAbortMarks(): void
    {
        $out = $this->renderFixtureScenario('Deliberately Failing');

        $this->assertStringContainsString('Story: Deliberately Failing (Failed)', $out);
        $this->assertStringContainsString('[+] Given a rendering fixture', $out);
        $this->assertStringContainsString('[-] When this rendering step fails', $out);
        $this->assertStringContainsString('Failed: rendering fixture failure on purpose', $out);
        $this->assertStringContainsString('[A] Then this rendering step passes', $out);
        $this->assertStringContainsString('[A] And this rendering step passes too', $out);
    }

    public function testUndefinedStepsRenderPendingMarks(): void
    {
        $out = $this->renderFixtureScenario('Deliberately Pending');

        $this->assertStringContainsString('Story: Deliberately Pending (Pending)', $out);
        $this->assertStringContainsString('[P] Then nobody ever defined this step', $out);
        $this->assertStringContainsString('[P] And nobody defined this one either', $out);
    }

    public function testSkippedScenarioRendersSkipAndAbortMarks(): void
    {
        $out = $this->renderFixtureScenario('Deliberately Skipped');

        $this->assertStringContainsString('Story: Deliberately Skipped (Skipped)', $out);
        $this->assertStringContainsString('[S] When this rendering step is skipped', $out);
        $this->assertStringContainsString('Skipped: rendering fixture skip on purpose', $out);
        $this->assertStringContainsString('[A] Then this rendering step passes', $out);
    }

    public function testIncompleteStepRendersPendingWithExplanation(): void
    {
        $out = $this->renderFixtureScenario('Deliberately Incomplete');

        $this->assertStringContainsString('Story: Deliberately Incomplete (Pending)', $out);
        $this->assertStringContainsString('[P] When this rendering step is incomplete', $out);
        $this->assertStringContainsString('Pending: rendering fixture incomplete on purpose', $out);
    }

    public function testDataDrivenScenarioRendersMultiMarksAndDataSetFailures(): void
    {
        $out = $this->renderFixtureScenario('Multi With Failing Row');

        $this->assertStringContainsString('Story: Multi With Failing Row (Multi)', $out);
        $this->assertStringContainsString('[M] When the rendering fixture stores <value>', $out);
        $this->assertStringContainsString('DataSets:', $out);
        $this->assertStringContainsString('DataSet(1): Failed', $out);
    }

    /**
     * Run one fixture scenario for real, then render the recorded story
     * through the subscriber into a captured stream.
     *
     * Only the fixture scenario's OWN outcome signals are swallowed: the
     * failed/pending/skipped fixtures throw AssertionFailedError subclasses
     * (IncompleteTestError, SkippedWithMessageException) by design. Anything
     * else — a missing .suite file (RuntimeException from parsedSuiteFor()),
     * an unresolvable @stepClass, a bad fixture setup — propagates and fails
     * THIS test at the real cause instead of a confusing "'' does not
     * contain 'Total Asserts:'" further down.
     */
    private function renderFixtureScenario(string $scenario): string
    {
        // The in-process scenario run below resets PHPUnit's SHARED static
        // assertion counter around every step (Module::snapshotAssertCount()
        // -> Assert::resetCount()), and PHPUnit only snapshots that counter
        // once the enclosing test method returns (TestRunner does
        // resetCount() before, addToAssertionCount(getCount()) after). Any
        // assertion this test performs BEFORE renderFixtureScenario() would
        // therefore be erased — with failOnRisky=true, asserting only before
        // it would even flag the test as risky. We restore the counter so
        // assertions made before this helper still count; assertions made
        // AFTER it (the normal pattern in this class) are unaffected.
        $assertsBeforeFixtureRun = Assert::getCount();

        $fixture = new RenderingCoverageTest('testScenario');

        try {
            $fixture->testScenario($scenario);
        } catch (AssertionFailedError|IncompleteTestError|SkippedTest) {
            // failing/pending/skipped scenarios throw by design
        }

        Assert::resetCount();
        for ($i = 0; $i < $assertsBeforeFixtureRun; $i++) {
            Assert::assertTrue(true, 'restore the enclosing test\'s earlier assertion count');
        }

        // renderScenario() takes the recorded outcome from
        // ScenarioResultRegistry itself; if the fixture run above failed to
        // record one, the rendered output stays empty and the
        // 'Total Asserts:' assertion below fails.
        $stream = fopen('php://temp', 'w+');
        $this->assertIsResource($stream);

        $subscriber = new BDDSubscriber(new BDDPrinter('never', $stream));
        $subscriber->renderScenario(RenderingCoverageTest::class, 'testScenario', 0);

        rewind($stream);
        $rendered = (string) stream_get_contents($stream);
        fclose($stream);

        $this->assertStringContainsString('Total Asserts:', $rendered);

        return $rendered;
    }
}
