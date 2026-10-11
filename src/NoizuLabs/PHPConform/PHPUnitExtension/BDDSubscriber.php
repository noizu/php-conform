<?php

namespace NoizuLabs\PHPConform\PHPUnitExtension;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

final class BDDSubscriber implements FinishedSubscriber
{
    public function __construct(private readonly BDDPrinter $printer)
    {
    }

    public function notify(Finished $event): void
    {
        $test = $event->test();

        if (!$test instanceof TestMethod) {
            return;
        }

        $class = $test->className();

        if (!class_exists($class) || !is_subclass_of($class, ScenarioSuite::class)) {
            return;
        }

        $story = ScenarioResultRegistry::take($class, $test->methodName());
        if ($story === null) {
            return;
        }

        $story['numAsserts'] = $event->numberOfAssertionsPerformed();
        $story['title'] = $test->methodName() === 'testScenario' && isset($story['scenario'])
            ? $story['scenario']
            : $test->methodName();

        $this->printer->render($story);
    }
}
