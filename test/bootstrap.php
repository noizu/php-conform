<?php

declare(strict_types=1);

namespace NoizuLabs\PHPConform\Tests;

/**
 * Optional bootstrap for the PHP-Conform development suite.
 *
 * Selenium support is optional: the pimple container and webdriver client
 * are only wired up when the packages are present.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
} else {
    trigger_error('Unable to locate required autoloader for the NoizuLabs/PHPConform library');
}

$containerClass = '\Pimple\Container';
$webdriverClass = '\WebDriver\WebDriver';

if (class_exists($containerClass)) {
    $noizulabs_phpconform_container = new $containerClass();
    $noizulabs_phpconform_container['SeleniumHost'] = getenv('SELENIUM_SERVER_HOST') ?: null;

    if (class_exists($webdriverClass)) {
        $noizulabs_phpconform_container['WebDriver'] = static fn () => static fn ($host) => new \WebDriver\WebDriver($host);
    }
}
