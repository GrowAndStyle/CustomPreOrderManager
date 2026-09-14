<?php declare(strict_types=1);

namespace CustomPreOrderManager\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class CustomPreOrderManagerExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Service-Konfiguration wird über services.xml geladen
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            'rate_limiter' => [
                'preorder_notify' => [
                    'policy' => 'fixed_window',
                    'limit' => 5,
                    'interval' => '1 minute',
                ],
            ],
        ]);
    }
}
