<?php declare(strict_types=1);

namespace CustomPreOrderManager\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class CustomPreOrderManagerExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');
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

    public function getAlias(): string
    {
        return 'custom_pre_order_manager';
    }
}
