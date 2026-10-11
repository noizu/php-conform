<?php

namespace NoizuLabs\PHPConform\PHPUnitExtension;

use PHPUnit\Framework\Assert;

/**
 * The Module class is used as a shunt that sits on top of other framework classes.
 * We use reflection to dig into otherwise protected or private methods and members
 * due to the lack of a robust plugin system for phpunit.
 */
abstract class Module extends Assert
{
    protected bool $_noizuRegistered = false;
    protected mixed $_noizuOwner = null;
    protected bool $_noizuDirectWrite = false;

    public function register(mixed $owner, bool $directWrite = false)
    {
        $this->_noizuRegistered = true;
        $this->_noizuOwner = $owner;
        $this->_noizuDirectWrite = $directWrite;
    }

    public function getOwner()
    {
        return $this->_noizuOwner;
    }

    /**
     * Number of PHPUnit assertions performed since the counter was last reset.
     * Step modules extend Assert directly, so their assertions increment the
     * shared static counter which we snapshot between steps.
     */
    public function assertionsPerformed(): int
    {
        return self::getCount();
    }

    public function resetAssertionCount(): void
    {
        self::resetCount();
    }

    public function &__get($arg)
    {
        if ($this->_noizuRegistered) {
            if (!$this->_noizuDirectWrite) {
                if (isset($this->_noizuOwner->_noizuVariableSpace)) {
                    if (isset($this->_noizuOwner->_noizuVariableSpace[$arg])) {
                        return $this->_noizuOwner->_noizuVariableSpace[$arg];
                    }
                }
                $nullValue = null;
                return $nullValue;
            }

            if (method_exists($this->_noizuOwner, 'getReference')) {
                return $this->_noizuOwner->getReference($arg);
            }

            try {
                $r = new \ReflectionObject($this->_noizuOwner);
                $p = $r->getProperty($arg);
                $p->setAccessible(true);
                $value = $p->getValue($this->_noizuOwner);
                return $value;
            } catch (\Exception $e) {
                // fall through
            }
        } else {
            trigger_error(get_class($this) . ' was used without registering back to test case.', E_USER_ERROR);
        }
        trigger_error(get_class($this) . " does not have a member named '$arg'");
        throw new \Exception(get_class($this) . " does not have a member named '$arg'");
    }

    public function __set($arg, $value)
    {
        if ($this->_noizuRegistered) {
            if (!$this->_noizuDirectWrite) {
                if (!isset($this->_noizuOwner->_noizuVariableSpace)) {
                    $this->_noizuOwner->_noizuVariableSpace = [];
                }
                $this->_noizuOwner->_noizuVariableSpace[$arg] = $value;
            } else {
                if (method_exists($this->_noizuOwner, 'getReference')) {
                    // Write through the by-reference return value.
                    $ref =& $this->_noizuOwner->getReference($arg);
                    $ref = $value;
                    return;
                }

                try {
                    $r = new \ReflectionObject($this->_noizuOwner);
                    $p = $r->getProperty($arg);
                    $p->setAccessible(true);
                    $p->setValue($this->_noizuOwner, $value);
                } catch (\Exception $e) {
                    $this->_noizuOwner->$arg = $value;
                }
            }
        } else {
            trigger_error(get_class($this) . ' was used without registering back to test case.', E_USER_ERROR);
        }
    }

    public function __call($func, $args)
    {
        if ($this->_noizuRegistered) {
            $r = new \ReflectionObject($this->_noizuOwner);
            $method = $r->getMethod($func);
            $method->setAccessible(true);
            return $method->invokeArgs($this->_noizuOwner, $args);
        }

        trigger_error(get_class($this) . ' was used without registering back to test case.', E_USER_ERROR);
        throw new \Exception(get_class($this) . ' was used without registering back to test case.');
    }
}
