<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests;

use PHPUnit\Framework\TestCase;

/**
 * End-to-end coverage of the story rendering: runs the rendering-coverage
 * fixture suite (deliberately failing/pending/skipped/multi scenarios,
 * excluded from this suite's own run) in an isolated child phpunit process
 * with the BDDExtension registered, and asserts that every documented
 * output mark is actually rendered through ScenarioSuite -> registry ->
 * event system -> BDDSubscriber -> BDDPrinter.
 */
class ScenarioRenderingOutputTest extends TestCase
{
    public function testChildRunRendersEveryDocumentedMark(): void
    {
        $root = dirname(__DIR__);

        $command = sprintf(
            '%s %s --configuration %s --no-progress 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($root . '/vendor/bin/phpunit'),
            escapeshellarg(__DIR__ . '/Rendering/phpunit.xml')
        );

        $outputLines = [];
        $exitCode = 0;
        exec($command, $outputLines, $exitCode);
        $output = implode("\n", $outputLines);

        // The child run must have actually run: five scenarios' worth of
        // story blocks, not an empty or crashed process.
        $this->assertStringContainsString('Story: Deliberately Failing (Failed)', $output, $output);
        $this->assertNotSame(0, $exitCode, "child phpunit run unexpectedly green:\n$output");

        // [+]: passing steps (and title-bar status).
        $this->assertStringContainsString('[+] Given a rendering fixture', $output, $output);

        // [-]: the deliberately failing step, with its exception rendered.
        $this->assertStringContainsString('[-] When this rendering step fails', $output, $output);
        $this->assertStringContainsString('Failed: rendering fixture failure on purpose', $output, $output);

        // [A]: steps after a failure (or a skip) are aborted, not run.
        $this->assertStringContainsString('[A] Then this rendering step passes', $output, $output);
        $this->assertStringContainsString('[A] And this rendering step passes too', $output, $output);

        // [P]: undefined steps leave the scenario pending/incomplete.
        $this->assertStringContainsString('Story: Deliberately Pending (Pending)', $output, $output);
        $this->assertStringContainsString('[P] Then nobody ever defined this step', $output, $output);

        // [P] via markTestIncomplete(), the other pending route.
        $this->assertStringContainsString('[P] When this rendering step is incomplete', $output, $output);
        $this->assertStringContainsString('rendering fixture incomplete on purpose', $output, $output);

        // [S]: the skipped step, subsequent steps aborted.
        $this->assertStringContainsString('Story: Deliberately Skipped (Skipped)', $output, $output);
        $this->assertStringContainsString('[S] When this rendering step is skipped', $output, $output);
        $this->assertStringContainsString('Skipped: rendering fixture skip on purpose', $output, $output);

        // [M]: data-driven scenario renders the data-set block; the second
        // row fails on purpose so the failures sub-block is exercised too.
        $this->assertStringContainsString('Story: Multi With Failing Row (Multi)', $output, $output);
        $this->assertStringContainsString('[M] When the rendering fixture stores <value>', $output, $output);
        $this->assertStringContainsString('DataSets:', $output, $output);
        $this->assertStringContainsString('DataSet(1): Failed', $output, $output);
    }
}
