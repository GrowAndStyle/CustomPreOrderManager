<?php declare(strict_types=1);

use Composer\Autoload\ClassLoader;

// Shopware-Root ermitteln
$projectDir = $_SERVER['PROJECT_ROOT'] ?? $_SERVER['SHOPWARE_ROOT'] ?? dirname(__DIR__, 4);

require_once $projectDir . '/vendor/autoload.php';

$loader = null;
foreach (spl_autoload_functions() as $fn) {
    if (is_array($fn) && $fn[0] instanceof ClassLoader) {
        $loader = $fn[0];
        break;
    }
}

if ($loader === null) {
    throw new \RuntimeException('Composer ClassLoader nicht gefunden.');
}

$pluginDir = dirname(__DIR__);
$loader->addPsr4('CustomPreOrderManager\\', $pluginDir . '/src/');
$loader->addPsr4('CustomPreOrderManager\\Tests\\', $pluginDir . '/tests/');
