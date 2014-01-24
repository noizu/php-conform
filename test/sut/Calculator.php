<?php
/**
 * Simple Calculator Class for testing BDD suite.
 */

class Calculator
{

    protected $_total = 0;

    public function add($arg1, $arg2)
    {
        $this->_total = ($arg1 + $arg2);
    }

    public function multiply($arg1, $arg2)
    {
        $this->_total = $arg1 * $arg2;
    }

    public function equals()
    {
        return $this->_total;
    }
}
