<?php

declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

$projectDir = $_SERVER['PROJECT_ROOT'] ?? $_SERVER['SHOPWARE_ROOT'] ?? dirname(__DIR__, 4);
$loader = (new TestBootstrapper())
    ->setProjectDir($projectDir)
    ->addCallingPlugin()
    ->addActivePlugins('CustomPreOrderManager')
    ->setForceInstallPlugins(true)
    ->bootstrap();

return $loader;
