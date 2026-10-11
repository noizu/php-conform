<?php

namespace NoizuLabs\PHPConform\PHPUnitExtension;

/**
 * Bridges scenario execution details (recorded while a scenario runs) to
 * the BDD output extension (notified by PHPUnit's event system after the
 * test finished). FIFO keyed by "class::method": scenarios run in the same
 * process, one dataset at a time, so take() consumes recordings in order.
 */
final class ScenarioResultRegistry
{
    /** @var array<string, list<array<string, mixed>>> */
    private static array $stories = [];

    /**
     * @param array<string, mixed> $story
     */
    public static function record(string $class, string $method, array $story): void
    {
        self::$stories[$class . '::' . $method][] = $story;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function take(string $class, string $method): ?array
    {
        $key = $class . '::' . $method;
        if (empty(self::$stories[$key])) {
            return null;
        }
        return array_shift(self::$stories[$key]);
    }
}
