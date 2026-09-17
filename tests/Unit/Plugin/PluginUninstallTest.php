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
 * Testet alle 5 Lifecycle-Methoden und stellt sicher, dass bei keepUserData=false
 * sowohl das CustomField-Set als auch system_config und custom_fields an Produkten
 * bereinigt werden.
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
        $connection->expects(static::exactly(5))
            ->method('executeStatement');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with(Connection::class)
            ->willReturn($connection);

        $this->plugin->setContainer($container);

        $this->plugin->uninstall($context);
    }
}
