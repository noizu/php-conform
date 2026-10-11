<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests;

use PHPUnit\Framework\TestCase;

class StandardTest extends TestCase
{
    public function testStandardStrawberry()
    {
        $this->assertTrue(true);
    }

    public function testStandardApple()
    {
        $this->assertEquals('lol', 'lol');
    }

    public function testStandardBananna()
    {
        $this->assertEquals(123, 123);
    }
}
