<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests;

use NoizuLabs\PHPConform\PHPUnitExtension\ScenarioSuite;
use NoizuLabs\PHPConform\PHPUnitExtension\StepAbstract;

/**
 * Regression guard for the setUp() ordering broken by the PHPUnit 12
 * lazy-initialization workaround (TestCase::__construct() was made final):
 * suite state ($this->_storyName, $this->_scenarios, $this->_stepRunner,
 * $this->container) must be populated BEFORE subclass setUp() code runs,
 * as it was when ScenarioSuite still initialized in its constructor.
 *
 * This fixture deliberately snapshots its state BEFORE calling
 * parent::setUp() — the strictest (constructor-era) ordering a consumer
 * can rely on. If initialization ever regresses to lazy-only (inside
 * testScenario()) or to parent::setUp()-only, the scenario below fails.
 *
 * @stepClass ScenarioSetUpOrderSteps
 */
class ScenarioSetUpOrderFixtureTest extends ScenarioSuite
{
    /** @var array<string, mixed>|null */
    public static ?array $stateBeforeParentSetUp = null;
    /** @var array<string, mixed>|null */
    public static ?array $stateAfterParentSetUp = null;

    protected function setUp(): void
    {
        // Snapshot BEFORE parent::setUp(): proves ScenarioSuite's #[Before]
        // initialization hook ran first, ahead of any subclass setUp() code.
        self::$stateBeforeParentSetUp = $this->snapshotSuiteState();
        parent::setUp();
        self::$stateAfterParentSetUp = $this->snapshotSuiteState();
    }

    /** @return array<string, mixed> */
    private function snapshotSuiteState(): array
    {
        return [
            'storyName' => $this->_storyName,
            'scenarioNames' => array_keys($this->_scenarios),
            'stepRunnerLoaded' => $this->_stepRunner !== null,
            'containerWired' => $this->container !== null,
        ];
    }
}

class ScenarioSetUpOrderSteps extends StepAbstract
{
    /**
     * @given the suite state was captured during setUp
     */
    public function assertStateWasReadyBeforeSubclassSetUp()
    {
        $state = ScenarioSetUpOrderFixtureTest::$stateBeforeParentSetUp;

        self::assertNotNull($state, 'suite state was NOT populated before subclass setUp() code ran');
        self::assertSame('setUpOrder', $state['storyName'], 'story name not parsed before setUp()');
        self::assertSame(
            ['State Ready During Set Up'],
            $state['scenarioNames'],
            'scenarios not parsed before setUp()'
        );
        self::assertTrue($state['stepRunnerLoaded'], '@stepClass modules not loaded before setUp()');
        self::assertSame(
            class_exists(\Pimple\Container::class),
            $state['containerWired'],
            'DI container wiring mismatch during setUp()'
        );
    }

    /**
     * @then the captured state proves initialization ran first
     */
    public function assertStateAlsoReadyAfterParentSetUp()
    {
        $state = ScenarioSetUpOrderFixtureTest::$stateAfterParentSetUp;

        self::assertNotNull($state, 'suite state was NOT populated after parent::setUp()');
        self::assertSame('setUpOrder', $state['storyName']);
        self::assertTrue($state['stepRunnerLoaded']);
    }
}
