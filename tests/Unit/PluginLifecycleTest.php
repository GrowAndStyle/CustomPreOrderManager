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
        $connection->method('fetchOne')->willReturn('type-id-123');
        $connection->method('fetchFirstColumn')->willReturn(['tpl-1', 'tpl-2']);
        $connection->expects(static::atLeast(10))->method('executeStatement');

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
        $context = Context::createDefaultContext();
        $installContext = $this->createMock(InstallContext::class);
        $installContext->method('getContext')->willReturn($context);

        $setRepository = $this->createMock(EntityRepository::class);
        $setSearchResult = $this->createMock(EntitySearchResult::class);
        $setSearchResult->method('first')->willReturn(null);
        $setRepository->method('search')->willReturn($setSearchResult);
        $setRepository->expects(static::once())->method('upsert');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn(string $id) => $id === 'custom_field_set.repository');
        $container->method('get')->with('custom_field_set.repository')->willReturn($setRepository);

        $plugin->setContainer($container);
        $plugin->install($installContext);
    }

    public function testEnsureCustomFieldsUpdatesExistingSetAndRelations(): void
    {
        $plugin = new CustomPreOrderManager(true, '');
        $context = Context::createDefaultContext();
        $installContext = $this->createMock(InstallContext::class);
        $installContext->method('getContext')->willReturn($context);

        $existingField = $this->createMock(CustomFieldEntity::class);
        $existingField->method('getName')->willReturn('custom_preorder_active');
        $existingField->method('getId')->willReturn('field-123');
        $fieldCollection = new CustomFieldCollection([$existingField]);

        $existingRelation = $this->createMock(CustomFieldSetRelationEntity::class);
        $existingRelation->method('getEntityName')->willReturn('product');
        $existingRelation->method('getId')->willReturn('relation-123');
        $relationCollection = new CustomFieldSetRelationCollection([$existingRelation]);

        $existingSet = $this->createMock(CustomFieldSetEntity::class);
        $existingSet->method('getId')->willReturn('set-123');
        $existingSet->method('getCustomFields')->willReturn($fieldCollection);
        $existingSet->method('getRelations')->willReturn($relationCollection);

        $setRepository = $this->createMock(EntityRepository::class);
        $setSearchResult = $this->createMock(EntitySearchResult::class);
        $setSearchResult->method('first')->willReturn($existingSet);
        $setRepository->method('search')->willReturn($setSearchResult);
        $setRepository->expects(static::once())->method('upsert')->with(static::callback(function (array $data) {
            $set = $data[0] ?? [];
            return ($set['id'] ?? null) === 'set-123'
                && ($set['relations'][0]['id'] ?? null) === 'relation-123';
        }));

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn(string $id) => $id === 'custom_field_set.repository');
        $container->method('get')->with('custom_field_set.repository')->willReturn($setRepository);

        $plugin->setContainer($container);
        $plugin->install($installContext);
    }

    public function testUpdateAndActivateCallEnsureCustomFields(): void
    {
        $plugin = new CustomPreOrderManager(true, '');
        $context = Context::createDefaultContext();
        $updateContext = $this->createMock(UpdateContext::class);
        $updateContext->method('getContext')->willReturn($context);
        $activateContext = $this->createMock(ActivateContext::class);
        $activateContext->method('getContext')->willReturn($context);

        $setRepository = $this->createMock(EntityRepository::class);
        $setSearchResult = $this->createMock(EntitySearchResult::class);
        $setSearchResult->method('first')->willReturn(null);
        $setRepository->method('search')->willReturn($setSearchResult);
        $setRepository->expects(static::exactly(2))->method('upsert');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn(string $id) => $id === 'custom_field_set.repository');
        $container->method('get')->with('custom_field_set.repository')->willReturn($setRepository);

        $plugin->setContainer($container);
        $plugin->update($updateContext);
        $plugin->activate($activateContext);
    }
}
