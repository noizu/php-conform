<?php

class StandardTest extends PHPUnit_Framework_TestCase
{

    public function testStandardStrawberry()
    {
        $this->assertTrue(true);
    }

    public function testStandardApple()
    {
       $this->assertEquals("lol", "lol");
    }

    public function testStandardBananna()
    {
       $this->assertEquals(123, 123);
    }

}

