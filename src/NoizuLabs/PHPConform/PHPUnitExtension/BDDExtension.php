<?php

namespace NoizuLabs\PHPConform\PHPUnitExtension;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Runner\Extension\Extension as PhpUnitExtension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * PHPUnit 10+ removed custom result printers; output is driven by the
 * event system. This extension is the modern replacement for the old
 * BDDPrinter TestListener swap: it listens for finished tests, and for
 * each PHP-Conform scenario renders the story block.
 *
 * Register it from phpunit.xml:
 *
 *   <extensions>
 *       <bootstrap class="\NoizuLabs\PHPConform\PHPUnitExtension\BDDExtension"/>
 *   </extensions>
 *
 * Optional parameters:
 *   colors="never|always|auto"  (default: auto — colors when stdout is a TTY)
 */
final class BDDExtension implements PhpUnitExtension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        // $configuration is required by the Extension contract but unused
        // here: the printer behaviour is driven entirely by parameters.
        $colors = 'auto';
        if ($parameters->has('colors')) {
            $colors = $parameters->string('colors');
        }

        $facade->registerSubscriber(new BDDSubscriber(new BDDPrinter($colors)));
    }
}
