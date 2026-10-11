<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\PHPUnitExtension\Attributes;

/**
 * Declare a step definition via PHP 8 attribute instead of a docblock.
 *
 *   #[Then('the total should be 5')]
 *   public function checkTotal() { ... }
 *
 * Equivalent to the docblock form "@then the total should be 5". Both
 * styles are supported; docblocks remain the canonical DSL.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Then
{
    public function __construct(public readonly string $then)
    {
    }
}
