<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests;

use NoizuLabs\PHPConform\PHPUnitExtension\StepAbstract;

class StepsForScenario extends StepAbstract
{
    /**
     * @then step functions included for only this test should be callable
     */
    public function StepClassIncludedPerScenario()
    {
    }
}
