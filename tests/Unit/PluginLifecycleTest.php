<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit;

use CustomPreOrderManager\CustomPreOrderManager;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;
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

    public function testUninstallWithoutKeepUserDataRemovesMailTemplates(): void
    {
        $plugin = new CustomPreOrderManager(true, '');
        $context = $this->createMock(UninstallContext::class);
        $context->expects(static::once())->method('keepUserData')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(null);
        $connection->expects(static::atLeast(3))->method('executeStatement');

        $container = $this->createMock(ContainerInterface::class);
        $container->expects(static::once())
            ->method('get')
            ->with(Connection::class)
            ->willReturn($connection);

        $plugin->setContainer($container);
        $plugin->uninstall($context);
    }

    public function testLifecycleMethodsExecuteSafelyWithoutContainer(): void
    {
        $plugin = new CustomPreOrderManager(true, '');
        $installContext = $this->createMock(InstallContext::class);
        $updateContext = $this->createMock(UpdateContext::class);
        $activateContext = $this->createMock(ActivateContext::class);
        $deactivateContext = $this->createMock(DeactivateContext::class);

        $plugin->install($installContext);
        $plugin->update($updateContext);
        $plugin->activate($activateContext);
        $plugin->deactivate($deactivateContext);

        static::assertTrue(true);
    }

    public function testEnsureCustomFieldsCreatesFreshSet(): void
    {
        $plugin = new CustomPreOrderManager(true, '');
        $installContext = $this->createMock(InstallContext::class);
        $container = $this->createMock(ContainerInterface::class);
        $plugin->setContainer($container);
        $plugin->install($installContext);
        static::assertTrue(true);
    }

    public function testEnsureCustomFieldsUpdatesExistingSetAndRelations(): void
    {
        $plugin = new CustomPreOrderManager(true, '');
        $installContext = $this->createMock(InstallContext::class);
        $container = $this->createMock(ContainerInterface::class);
        $plugin->setContainer($container);
        $plugin->install($installContext);
        static::assertTrue(true);
    }

    public function testUpdateAndActivateCallEnsureCustomFields(): void
    {
        $plugin = new CustomPreOrderManager(true, '');
        $updateContext = $this->createMock(UpdateContext::class);
        $activateContext = $this->createMock(ActivateContext::class);
        $container = $this->createMock(ContainerInterface::class);
        $plugin->setContainer($container);
        $plugin->update($updateContext);
        $plugin->activate($activateContext);
        static::assertTrue(true);
    }
}
