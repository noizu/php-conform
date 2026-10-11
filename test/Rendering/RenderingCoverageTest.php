<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests\Rendering;

use NoizuLabs\PHPConform\PHPUnitExtension\ScenarioSuite;

/**
 * Fixture suite whose scenarios deliberately cover every outcome the
 * BDD printer can render (passed, failed, pending, skipped, aborted,
 * multi). This file is deliberately NOT part of the main test suite
 * (the root phpunit.xml excludes this directory): these scenarios are
 * meant to fail/skip, and are executed by ScenarioRenderingOutputTest
 * in an isolated child phpunit process whose output is asserted on.
 *
 * @stepClass RenderingSteps
 */
class RenderingCoverageTest extends ScenarioSuite
{
}
