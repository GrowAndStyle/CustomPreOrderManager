<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Integration;

use CustomPreOrderManager\CustomPreOrderManager;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomPreOrderManagerTest extends TestCase
{
    private CustomPreOrderManager $plugin;

    protected function setUp(): void
    {
        $this->plugin = new CustomPreOrderManager(true, '');
    }

    public function testInstall(): void
    {
        $context = $this->createMock(InstallContext::class);
        $this->plugin->install($context);
        static::assertTrue(true);
    }

    public function testUpdate(): void
    {
        $context = $this->createMock(UpdateContext::class);
        $this->plugin->update($context);
        static::assertTrue(true);
    }

    public function testActivate(): void
    {
        $context = $this->createMock(ActivateContext::class);
        $this->plugin->activate($context);
        static::assertTrue(true);
    }

    public function testDeactivate(): void
    {
        $context = $this->createMock(DeactivateContext::class);
        $this->plugin->deactivate($context);
        static::assertTrue(true);
    }

    public function testUninstallKeepUserData(): void
    {
        $context = $this->createMock(UninstallContext::class);
        $context->method('keepUserData')->willReturn(true);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects(static::never())->method('get');
        $this->plugin->setContainer($container);

        $this->plugin->uninstall($context);
    }

    public function testUninstallRemoveUserData(): void
    {
        $context = $this->createMock(UninstallContext::class);
        $context->method('keepUserData')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::exactly(4))
            ->method('executeStatement');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with(Connection::class)
            ->willReturn($connection);

        $this->plugin->setContainer($container);

        $this->plugin->uninstall($context);
    }
}
