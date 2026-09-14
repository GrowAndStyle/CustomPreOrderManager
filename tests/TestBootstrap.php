<?php declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

$loader = (new TestBootstrapper())
    ->addCallingRegister()
    ->addActivePlugins('CustomPreOrderManager')
    ->setForceInstallPlugins(true)
    ->bootstrap()
    ->getClassLoader();

$loader->addPsr4('CustomPreOrderManager\\Tests\\', __DIR__);
