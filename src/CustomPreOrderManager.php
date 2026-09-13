<?php declare(strict_types=1);

namespace CustomPreOrderManager;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomPreOrderManager extends Plugin
{
    public function install(InstallContext $context): void
    {
        $this->ensureCustomFields($context->getContext());
    }

    public function update(UpdateContext $context): void
    {
        $this->ensureCustomFields($context->getContext());
    }

    public function activate(ActivateContext $context): void
    {
        $this->ensureCustomFields($context->getContext());
    }

    public function deactivate(DeactivateContext $context): void
    {
        // Deaktivierungslogik
    }

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        // 1. Eigene Tabellen entfernen (Reihenfolge wegen FK-Constraints)
        $connection->executeStatement('DROP TABLE IF EXISTS `custom_preorder_waitlist`');

        // 2. CustomFieldSets entfernen (Kaskadiert zu custom_field und custom_field_set_relation)
        $connection->executeStatement(
            "DELETE FROM `custom_field_set` WHERE `name` = 'custom_preorder_set'"
        );

        // 3. Eigene Mail-Templates und Typen entfernen
        $templateTypes = ['preorder_waitlist_doi', 'preorder_waitlist_available', 'preorder_vip_order_placed'];
        foreach ($templateTypes as $type) {
            $typeId = $connection->fetchOne(
                'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :type',
                ['type' => $type]
            );
            if ($typeId) {
                $templateIds = $connection->fetchFirstColumn(
                    'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :typeId',
                    ['typeId' => $typeId]
                );
                foreach ($templateIds as $tplId) {
                    $connection->executeStatement('DELETE FROM `mail_template_translation` WHERE `mail_template_id` = :id', ['id' => $tplId]);
                    $connection->executeStatement('DELETE FROM `mail_template` WHERE `id` = :id', ['id' => $tplId]);
                }
                $connection->executeStatement('DELETE FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :typeId', ['typeId' => $typeId]);
                $connection->executeStatement('DELETE FROM `mail_template_type` WHERE `id` = :typeId', ['typeId' => $typeId]);
            }
        }

        // 4. System-Config entfernen
        $connection->executeStatement(
            "DELETE FROM `system_config` WHERE `configuration_key` LIKE 'CustomPreOrderManager.config.%'"
        );
    }

    private function ensureCustomFields(Context $context): void
    {
        if (!$this->container || !$this->container->has('custom_field_set.repository')) {
            return;
        }

        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'custom_preorder_set'));
        $criteria->addAssociation('relations');
        $criteria->addAssociation('customFields');

        $existingSet = $customFieldSetRepository->search($criteria, $context)->first();
        $setId = $existingSet ? $existingSet->getId() : Uuid::randomHex();

        $existingFieldIds = [];
        if ($existingSet && $existingSet->getCustomFields()) {
            foreach ($existingSet->getCustomFields() as $field) {
                $existingFieldIds[$field->getName()] = $field->getId();
            }
        }

        $relationData = [
            'entityName' => 'product',
        ];
        if ($existingSet && $existingSet->getRelations()) {
            foreach ($existingSet->getRelations() as $rel) {
                if ($rel->getEntityName() === 'product') {
                    $relationData['id'] = $rel->getId();
                    break;
                }
            }
        }

        $customFieldSetRepository->upsert([
            [
                'id' => $setId,
                'name' => 'custom_preorder_set',
                'config' => [
                    'label' => [
                        'de-DE' => 'Vorbestellung (Pre-Order)',
                        'en-GB' => 'Pre-Order',
                    ],
                    'translated' => true,
                ],
                'position' => 100,
                'relations' => [
                    $relationData,
                ],
                'customFields' => [
                    [
                        'id' => $existingFieldIds['custom_preorder_active'] ?? Uuid::randomHex(),
                        'name' => 'custom_preorder_active',
                        'type' => CustomFieldTypes::BOOL,
                        'config' => [
                            'label' => [
                                'de-DE' => 'Vorbestellung aktivieren',
                                'en-GB' => 'Enable Pre-Order',
                            ],
                            'componentName' => 'sw-field',
                            'type' => 'switch',
                            'customFieldType' => 'switch',
                            'customFieldPosition' => 1,
                        ],
                    ],
                    [
                        'id' => $existingFieldIds['custom_preorder_release_date'] ?? Uuid::randomHex(),
                        'name' => 'custom_preorder_release_date',
                        'type' => CustomFieldTypes::DATETIME,
                        'config' => [
                            'label' => [
                                'de-DE' => 'Erscheinungsdatum',
                                'en-GB' => 'Release Date',
                            ],
                            'componentName' => 'sw-datepicker',
                            'type' => 'date',
                            'dateType' => 'date',
                            'customFieldType' => 'date',
                            'customFieldPosition' => 2,
                        ],
                    ],
                    [
                        'id' => $existingFieldIds['custom_preorder_release_text'] ?? Uuid::randomHex(),
                        'name' => 'custom_preorder_release_text',
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'label' => [
                                'de-DE' => 'Hinweistext Frontend',
                                'en-GB' => 'Frontend Display Text',
                            ],
                            'componentName' => 'sw-field',
                            'type' => 'text',
                            'customFieldType' => 'text',
                            'placeholder' => [
                                'de-DE' => 'z. B. Lieferbar ab Mitte Oktober 2026',
                                'en-GB' => 'e.g. Expected October 2026',
                            ],
                            'customFieldPosition' => 3,
                        ],
                    ],
                    [
                        'id' => $existingFieldIds['custom_preorder_inbound_stock'] ?? Uuid::randomHex(),
                        'name' => 'custom_preorder_inbound_stock',
                        'type' => CustomFieldTypes::INT,
                        'config' => [
                            'label' => [
                                'de-DE' => 'Zulaufmenge (Lieferanten-Kontingent)',
                                'en-GB' => 'Inbound Quota',
                            ],
                            'componentName' => 'sw-field',
                            'type' => 'number',
                            'numberType' => 'int',
                            'customFieldType' => 'number',
                            'min' => 0,
                            'customFieldPosition' => 4,
                        ],
                    ],
                    [
                        'id' => $existingFieldIds['custom_preorder_sold_count'] ?? Uuid::randomHex(),
                        'name' => 'custom_preorder_sold_count',
                        'type' => CustomFieldTypes::INT,
                        'config' => [
                            'label' => [
                                'de-DE' => 'Bereits vorbestellte Menge',
                                'en-GB' => 'Sold Pre-Order Quantity',
                            ],
                            'componentName' => 'sw-field',
                            'type' => 'number',
                            'numberType' => 'int',
                            'customFieldType' => 'number',
                            'min' => 0,
                            'disabled' => true,
                            'customFieldPosition' => 5,
                        ],
                    ],
                ],
            ],
        ], $context);
    }
}
