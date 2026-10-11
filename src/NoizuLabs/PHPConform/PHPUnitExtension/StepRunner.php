<?php

namespace NoizuLabs\PHPConform\PHPUnitExtension;

class StepRunner extends StepAbstract
{
    public function __construct(
        ?StepAbstract $scenarioModules = null,
        ?StepAbstract $storyModules = null,
        mixed $suiteModules = null
    ) {
        if (!empty($scenarioModules)) {
            $this->addStepModule($scenarioModules);
        }

        if (!empty($storyModules)) {
            $this->addStepModule($storyModules);
        }

        if (!empty($suiteModules)) {
            $mod = new StepRunner();
            $mod->registerClassSteps($suiteModules);
            $this->addStepModule($mod);
        }

        $this->register($suiteModules);

        parent::__construct();
    }
}
