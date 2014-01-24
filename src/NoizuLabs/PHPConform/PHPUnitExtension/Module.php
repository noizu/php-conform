<?php

namespace NoizuLabs\PHPConform\PHPUnitExtension;


/**
 * The Module class is used as a shunt that sits ontop of other php framework classes. We use reflection to dig into otherwise protected or private methods and members due to the lack of a robust plugin system for phpunit.
 */
abstract class Module extends \PHPUnit_Framework_Assert
{
    protected $_noizuRegistered = false;
    protected $_noizuOwner = null;
    protected $_noizuDirectWrite = false;

    public function register(&$owner, $directWrite = false)
    {
        $this->_noizuRegistered = true;
        $this->_noizuOwner = $owner;
        $this->_noizuDirectWrite = $directWrite;
    }

    public function getOwner()
    {
        return $this->_noizuOwner;
    }

    public function &__get($arg)
    {
        if ($this->_noizuRegistered) {
            if (!$this->_noizuDirectWrite) {
                if (isset($this->_noizuOwner->_noizuVariableSpace)) {

                    $nullValue = null;
                   
                    if(isset($this->_noizuOwner->_noizuVariableSpace[$arg])) {
                         return $this->_noizuOwner->_noizuVariableSpace[$arg];
                    } else {
                         return $nullValue;
                    }
                }
            } else {

                if(method_exists($this->_noizuOwner, "getReference")) {
                    return $this->_noizuOwner->getReference($arg);
                }

                try {
                    $r = new \ReflectionObject($this->_noizuOwner);
                    $p = $r->getProperty($arg);
                    $p->setAccessible(true);
                    return $p->getValue($this->_noizuOwner);

                } catch (\Exception $e) {

                }
            }
        } else {
            trigger_error(get_class($this) . " was used with out registering back to test case.", E_USER_ERROR);
        }
        trigger_error(get_class($this) . " does not have an member named '$arg'");
        throw new \Exception( get_class($this) . " does not have an member named '$arg'");
    }

    public function __set($arg, $value)
    {
        if ($this->_noizuRegistered) {
            if (!$this->_noizuDirectWrite) {
                if (!isset($this->_noizuOwner->_noizuVariableSpace)) {
                    $this->_noizuOwner->_noizuVariableSpace = array();
                }
                $this->_noizuOwner->_noizuVariableSpace[$arg] = $value;
            } else {

                try {
                    $r = new \ReflectionObject($this->_noizuOwner);
                    $p = $r->getProperty($arg);
                    $p->setAccessible(true);
                    return $p->setValue($this->_noizuOwner, $value);
                } catch (\Exception $e) {
                    $this->_noizuOwner->$arg = $value;
                }
            }
        } else {
            trigger_error(get_class($this) . " was used with out registering back to test case.", E_USER_ERROR);
        }
    }

    public function __call($func, $args)
    {
        if ($this->_noizuRegistered) {
            try {
                $r = new \ReflectionObject($this->_noizuOwner);
                $method = $r->getMethod($func);
                $method->setAccessible(true);
                return $method->invokeArgs($this->_noizuOwner, $args);
            } catch (\Exception $e) {
                // special handling
                throw($e);
            }
        } else {
            trigger_error(get_class($this) . " was used with out registering back to test case.", E_USER_ERROR);
        }
    }
}
