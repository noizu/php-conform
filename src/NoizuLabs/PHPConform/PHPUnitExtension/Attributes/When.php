<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\PHPUnitExtension\Attributes;

/**
 * Declare a step definition via PHP 8 attribute instead of a docblock.
 *
 *   #[When('running phpunit')]
 *   public function runningPhpunit() { ... }
 *
 * Equivalent to the docblock form "@when running phpunit". Both styles are
 * supported; docblocks remain the canonical DSL.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class When
{
    public function __construct(public readonly string $when)
    {
    }
}
