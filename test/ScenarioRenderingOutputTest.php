<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests;

use NoizuLabs\PHPConform\PHPUnitExtension\BDDPrinter;
use NoizuLabs\PHPConform\PHPUnitExtension\BDDSubscriber;
use NoizuLabs\PHPConform\Tests\Rendering\RenderingCoverageTest;
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
     * Run one fixture scenario for real (its failure/skip/incomplete
     * outcome is expected and swallowed), then render the recorded story
     * through the subscriber into a captured stream.
     */
    private function renderFixtureScenario(string $scenario): string
    {
        $fixture = new RenderingCoverageTest('testScenario');

        try {
            $fixture->testScenario($scenario);
        } catch (\Throwable) {
            // failing/pending/skipped scenarios throw by design
        }

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
