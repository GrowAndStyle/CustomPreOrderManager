<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Integration\DependencyInjection;

use CustomPreOrderManager\DependencyInjection\CustomPreOrderManagerExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CustomPreOrderManagerExtensionTest extends TestCase
{
    public function testLoad(): void
    {
        $extension = new CustomPreOrderManagerExtension();
        $container = new ContainerBuilder();
        $extension->load([], $container);

        static::assertTrue(true);
    }

    public function testPrepend(): void
    {
        $extension = new CustomPreOrderManagerExtension();
        $container = $this->createMock(ContainerBuilder::class);

        $container->expects(static::once())
            ->method('prependExtensionConfig')
            ->with(
                'framework',
                static::callback(function (array $config) {
                    return isset($config['rate_limiter']['preorder_notify'])
                        && $config['rate_limiter']['preorder_notify']['limit'] === 5
                        && $config['rate_limiter']['preorder_notify']['policy'] === 'fixed_window';
                })
            );

        $extension->prepend($container);
    }
}
