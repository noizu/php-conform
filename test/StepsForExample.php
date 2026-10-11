<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests;

use NoizuLabs\PHPConform\PHPUnitExtension\StepAbstract;

class StepsForExample extends StepAbstract
{
    /**
     * @then step functions included by the class should be callable
     */
    public function StepFunctionIncludedForClass()
    {
        // Module::__set with directWrite proxies this onto the owning
        // scenario suite, where suite-level steps can read it back.
        $this->outsideVar = true;
    }
}
