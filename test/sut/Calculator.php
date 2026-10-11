<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests\sut;

/**
 * Simple Calculator Class for testing the BDD suite.
 *
 * Integer arithmetic is the example suite's contract: every input the
 * scenarios feed in is an integer. The accumulator is still int|float so
 * fractional values accumulate honestly instead of being silently cast.
 */
class Calculator
{
    protected int|float $_total = 0;

    public function add(int|float $arg1, int|float $arg2): void
    {
        $this->_total = $arg1 + $arg2;
    }

    public function multiply(int|float $arg1, int|float $arg2): void
    {
        $this->_total = $arg1 * $arg2;
    }

    public function equals(): int|float
    {
        return $this->_total;
    }
}
