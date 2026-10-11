<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\PHPUnitExtension\Attributes;

/**
 * Declare a step definition via PHP 8 attribute instead of a docblock.
 *
 *   #[Given('a calculator')]
 *   public function createCalculator() { ... }
 *
 * Equivalent to the docblock form "@given a calculator". Both styles are
 * supported; docblocks remain the canonical DSL.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Then
{
    public function __construct(public readonly string $then)
    {
    }
}
