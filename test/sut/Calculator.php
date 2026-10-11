<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests\sut;

/**
 * Simple Calculator Class for testing the BDD suite.
 */
class Calculator
{
    protected int $_total = 0;

    public function add(int|float $arg1, int|float $arg2): void
    {
        $this->_total = (int) ($arg1 + $arg2);
    }

    public function multiply(int|float $arg1, int|float $arg2): void
    {
        $this->_total = (int) ($arg1 * $arg2);
    }

    public function equals(): int
    {
        return $this->_total;
    }
}
