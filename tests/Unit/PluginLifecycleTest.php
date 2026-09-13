<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit;

use CustomPreOrderManager\CustomPreOrderManager;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PluginLifecycleTest extends TestCase
{
    public function testUninstallWithKeepUserDataPreservesData(): void
    {
        $plugin = new CustomPreOrderManager(true, '');
        $context = $this->createMock(UninstallContext::class);
        $context->expects(static::once())->method('keepUserData')->willReturn(true);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects(static::never())->method('get');

        $plugin->setContainer($container);
        $plugin->uninstall($context);
    }

    public function testUninstallWithoutKeepUserDataDropsData(): void
    {
        $plugin = new CustomPreOrderManager(true, '');
        $context = $this->createMock(UninstallContext::class);
        $context->expects(static::once())->method('keepUserData')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::atLeast(3))->method('executeStatement');
        $connection->method('fetchOne')->willReturn(null);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects(static::once())
            ->method('get')
            ->with(Connection::class)
            ->willReturn($connection);

        $plugin->setContainer($container);
        $plugin->uninstall($context);
    }
}
