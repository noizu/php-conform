<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\PHPUnitExtension\Attributes;

/**
 * Mark a step definition sentence as a full regular expression and set its
 * delimiter, instead of using @pregmatch in the docblock.
 *
 *   #[PregMatch('#')]
 *   #[When('we store the value (.*)')]
 *   public function store($matches) { ... }
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class PregMatch
{
    public function __construct(public readonly string $delimiter)
    {
    }
}
