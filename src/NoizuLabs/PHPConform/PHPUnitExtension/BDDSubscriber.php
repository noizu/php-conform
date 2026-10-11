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

        $this->renderScenario($class, $test->methodName(), $event->numberOfAssertionsPerformed());
    }

    /**
     * Render the recorded story for a finished scenario test: take the
     * recorded outcome from the registry, normalise the title/assertion
     * count the same way notify() does for live events, and print it.
     * Split out of notify() so the rendering path can be exercised
     * in-process (see ScenarioRenderingOutputTest) without needing a
     * constructed PHPUnit event.
     */
    public function renderScenario(string $class, string $method, int $numAsserts): void
    {
        $story = ScenarioResultRegistry::take($class, $method);
        if ($story === null) {
            return;
        }

        $story['numAsserts'] = $numAsserts;
        $story['title'] = $method === 'testScenario' && isset($story['scenario'])
            ? $story['scenario']
            : $method;

        $this->printer->render($story);
    }
}
