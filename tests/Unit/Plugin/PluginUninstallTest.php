<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Plugin;

use CustomPreOrderManager\CustomPreOrderManager;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unit-Tests für den Plugin-Lifecycle und Uninstall.
 *
 * Testet alle 5 Lifecycle-Methoden und verifiziert per Argument-Matching,
 * dass uninstall(keepUserData=false) exakt die erwarteten SQL-Statements
 * in der richtigen Reihenfolge ausführt.
 */
class PluginUninstallTest extends TestCase
{
    private CustomPreOrderManager $plugin;

    protected function setUp(): void
    {
        $this->plugin = new CustomPreOrderManager(true, '');
    }

    public function testPluginClassExists(): void
    {
        static::assertInstanceOf(CustomPreOrderManager::class, $this->plugin);
    }

    public function testGetContainerExtension(): void
    {
        $extension = $this->plugin->getContainerExtension();
        static::assertInstanceOf(\CustomPreOrderManager\DependencyInjection\CustomPreOrderManagerExtension::class, $extension);
    }

    public function testInstallDoesNotThrow(): void
    {
        $context = $this->createMock(InstallContext::class);
        $this->plugin->install($context);
        static::assertTrue(true);
    }

    public function testUpdateDoesNotThrow(): void
    {
        $context = $this->createMock(UpdateContext::class);
        $this->plugin->update($context);
        static::assertTrue(true);
    }

    public function testActivateDoesNotThrow(): void
    {
        $context = $this->createMock(ActivateContext::class);
        $this->plugin->activate($context);
        static::assertTrue(true);
    }

    public function testDeactivateDoesNotThrow(): void
    {
        $context = $this->createMock(DeactivateContext::class);
        $this->plugin->deactivate($context);
        static::assertTrue(true);
    }

    public function testUninstallKeepUserDataSkipsCleanup(): void
    {
        $context = $this->createMock(UninstallContext::class);
        $context->method('keepUserData')->willReturn(true);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects(static::never())->method('get');
        $this->plugin->setContainer($container);

        $this->plugin->uninstall($context);
    }

    public function testUninstallRemoveUserDataExecutesAllCleanups(): void
    {
        $context = $this->createMock(UninstallContext::class);
        $context->method('keepUserData')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::exactly(8))
            ->method('executeStatement')
            ->withConsecutive(
                [static::stringContains('DELETE cf FROM `custom_field` cf')],
                [static::stringContains('DELETE cfsr FROM `custom_field_set_relation` cfsr')],
                [static::stringContains('DELETE FROM `custom_field_set`')],
                [static::stringContains('UPDATE `product_translation`')],
                [static::stringContains('UPDATE `product`')],
                [static::stringContains('DELETE ot FROM `order_tag` ot')],
                [static::stringContains('DELETE FROM `tag`')],
                [static::stringContains('DELETE FROM system_config')],
            );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with(Connection::class)
            ->willReturn($connection);

        $this->plugin->setContainer($container);
        $this->plugin->uninstall($context);
    }

    public function testUninstallToleratesMissingProductCustomFieldsColumn(): void
    {
        $context = $this->createMock(UninstallContext::class);
        $context->method('keepUserData')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('beginTransaction');
        $connection->expects(static::once())->method('commit');
        $connection->expects(static::never())->method('rollBack');

        // Simuliert Shopware 6.5+, wo die Tabelle product keine custom_fields Spalte besitzt
        $connection->method('executeStatement')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'UPDATE `product`')) {
                    throw new \RuntimeException("Unknown column 'custom_fields' in 'field list'");
                }
                return 1;
            });

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with(Connection::class)
            ->willReturn($connection);

        $this->plugin->setContainer($container);
        $this->plugin->uninstall($context);
    }

    public function testUninstallRollsBackTransactionOnError(): void
    {
        $context = $this->createMock(UninstallContext::class);
        $context->method('keepUserData')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('beginTransaction');
        $connection->expects(static::never())->method('commit');
        $connection->expects(static::once())->method('rollBack');

        $connection->method('executeStatement')
            ->willThrowException(new \RuntimeException('Database error during cleanup'));

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with(Connection::class)
            ->willReturn($connection);

        $this->plugin->setContainer($container);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error during cleanup');

        $this->plugin->uninstall($context);
    }
}
