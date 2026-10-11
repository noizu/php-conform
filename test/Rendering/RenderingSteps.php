<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests\Rendering;

use NoizuLabs\PHPConform\PHPUnitExtension\StepAbstract;

/**
 * Step definitions for the rendering-coverage fixture scenarios. Each
 * outcome flavour (pass, fail, incomplete, skip) is deliberately produced
 * so the BDD printer renders every documented step mark.
 */
class RenderingSteps extends StepAbstract
{
    public int $stored = 0;

    /**
     * @given a rendering fixture
     */
    public function setupRenderingFixture()
    {
    }

    /**
     * @when this rendering step fails
     */
    public function failingRenderingStep()
    {
        $this->assertTrue(false, 'rendering fixture failure on purpose');
    }

    /**
     * @when this rendering step is skipped
     */
    public function skippedRenderingStep()
    {
        $this->markTestSkipped('rendering fixture skip on purpose');
    }

    /**
     * @when this rendering step is incomplete
     */
    public function incompleteRenderingStep()
    {
        $this->markTestIncomplete('rendering fixture incomplete on purpose');
    }

    /**
     * @then this rendering step passes
     */
    public function passingRenderingStep()
    {
        $this->assertTrue(true);
    }

    /**
     * @then this rendering step passes too
     */
    public function passingRenderingStepToo()
    {
        $this->assertTrue(true);
    }

    /**
     * @when the rendering fixture stores $value
     */
    public function storeRenderingValue($value)
    {
        $this->stored = (int) $value;
        $this->assertTrue(true);
    }

    /**
     * @then the stored rendering value should be $expected
     */
    public function verifyStoredRenderingValue($expected)
    {
        $this->assertSame((int) $expected, $this->stored);
    }
}
