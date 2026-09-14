<?php declare(strict_types=1);

namespace CustomPreOrderManager\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class Migration1726200000AddPreOrderCustomFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1726200000;
    }

    public function update(Connection $connection): void
    {
        $setId = $connection->fetchOne(
            "SELECT `id` FROM `custom_field_set` WHERE `name` = 'custom_preorder_set'"
        );

        if (!$setId) {
            $setId = Uuid::randomBytes();
            $connection->insert('custom_field_set', [
                'id' => $setId,
                'name' => 'custom_preorder_set',
                'config' => json_encode([
                    'label' => ['de-DE' => 'Vorbestellung (Pre-Order)', 'en-GB' => 'Pre-Order'],
                    'translated' => true,
                ], JSON_THROW_ON_ERROR),
                'active' => 1,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
            ]);

            $connection->insert('custom_field_set_relation', [
                'id' => Uuid::randomBytes(),
                'custom_field_set_id' => $setId,
                'entity_name' => 'product',
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
            ]);
        }

        $fields = [
            ['name' => 'custom_preorder_active', 'type' => CustomFieldTypes::BOOL, 'config' => [
                'label' => ['de-DE' => 'Vorbestellung aktivieren', 'en-GB' => 'Enable Pre-Order'],
                'componentName' => 'sw-field', 'type' => 'switch', 'customFieldPosition' => 1,
            ]],
            ['name' => 'custom_preorder_release_date', 'type' => CustomFieldTypes::DATETIME, 'config' => [
                'label' => ['de-DE' => 'Erscheinungsdatum', 'en-GB' => 'Release Date'],
                'componentName' => 'sw-datepicker', 'dateType' => 'date', 'customFieldPosition' => 2,
            ]],
            ['name' => 'custom_preorder_release_text', 'type' => CustomFieldTypes::TEXT, 'config' => [
                'label' => ['de-DE' => 'Hinweistext Frontend', 'en-GB' => 'Frontend Display Text'],
                'componentName' => 'sw-field', 'type' => 'text',
                'placeholder' => ['de-DE' => 'z. B. Lieferbar ab Mitte Oktober 2026', 'en-GB' => 'e.g. Expected October 2026'],
                'customFieldPosition' => 3,
            ]],
            ['name' => 'custom_preorder_inbound_stock', 'type' => CustomFieldTypes::INT, 'config' => [
                'label' => ['de-DE' => 'Zulaufmenge (Lieferanten-Kontingent)', 'en-GB' => 'Inbound Quota'],
                'componentName' => 'sw-field', 'type' => 'number', 'numberType' => 'int', 'min' => 0,
                'customFieldPosition' => 4,
            ]],
            ['name' => 'custom_preorder_sold_count', 'type' => CustomFieldTypes::INT, 'config' => [
                'label' => ['de-DE' => 'Bereits vorbestellte Menge', 'en-GB' => 'Sold Pre-Order Quantity'],
                'componentName' => 'sw-field', 'type' => 'number', 'numberType' => 'int', 'min' => 0,
                'disabled' => true, 'customFieldPosition' => 5,
            ]],
        ];

        foreach ($fields as $field) {
            $exists = $connection->fetchOne(
                "SELECT `id` FROM `custom_field` WHERE `name` = :name",
                ['name' => $field['name']]
            );
            if (!$exists) {
                $connection->insert('custom_field', [
                    'id' => Uuid::randomBytes(),
                    'name' => $field['name'],
                    'type' => $field['type'],
                    'config' => json_encode($field['config'], JSON_THROW_ON_ERROR),
                    'active' => 1,
                    'set_id' => $setId,
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
                ]);
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // Keine destruktiven Änderungen während regulärer Updates
    }
}
