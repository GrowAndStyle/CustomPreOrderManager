<?php declare(strict_types=1);

namespace CustomPreOrderManager;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use CustomPreOrderManager\DependencyInjection\CustomPreOrderManagerExtension;

class CustomPreOrderManager extends Plugin
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new CustomPreOrderManagerExtension();
    }
    public function install(InstallContext $installContext): void
    {
        // Migrationen werden durch Shopware automatisch ausgeführt
    }

    public function update(UpdateContext $updateContext): void
    {
        // Schema-Updates werden über Migrationen gesteuert
    }

    public function activate(ActivateContext $activateContext): void
    {
        // Optionale Initialisierungslogik bei Plugin-Aktivierung
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        // Subscriber und Services werden automatisch durch den DI-Container deaktiviert
    }

    /**
     * Saubere Deinstallation mit Prüfung auf keepUserData.
     * Bei false werden CustomFields und Plugin-Konfigurationen per DBAL entfernt.
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        $connection->beginTransaction();
        try {
            // 1. Custom-Field-Set, Relationen und Felder in korrekter FK-Reihenfolge entfernen
            $connection->executeStatement("
                DELETE cf FROM `custom_field` cf
                INNER JOIN `custom_field_set` cfs ON cf.set_id = cfs.id
                WHERE cfs.name = 'custom_preorder_set'
            ");
            $connection->executeStatement("
                DELETE cfsr FROM `custom_field_set_relation` cfsr
                INNER JOIN `custom_field_set` cfs ON cfsr.set_id = cfs.id
                WHERE cfs.name = 'custom_preorder_set'
            ");
            $connection->executeStatement("
                DELETE FROM `custom_field_set` WHERE name = 'custom_preorder_set'
            ");

            $jsonRemoveSql = "JSON_REMOVE(
                `custom_fields`,
                '$.custom_preorder_active',
                '$.custom_preorder_release_date',
                '$.custom_preorder_release_text',
                '$.custom_preorder_inbound_stock',
                '$.custom_preorder_sold_count'
            )";

            // 2. Custom-Fields an betroffenen Produkt-Übersetzungen entfernen (Scope-Filter gegen Table-Locks)
            $connection->executeStatement("
                UPDATE `product_translation`
                SET `custom_fields` = {$jsonRemoveSql}
                WHERE `custom_fields` IS NOT NULL
                  AND JSON_CONTAINS_PATH(`custom_fields`, 'one', '$.custom_preorder_active', '$.custom_preorder_release_date') = 1
            ");

            // 3. Fallback: Custom-Fields an Produkten entfernen (falls Tabelle custom_fields Spalte besitzt)
            try {
                $connection->executeStatement("
                    UPDATE `product`
                    SET `custom_fields` = {$jsonRemoveSql}
                    WHERE `custom_fields` IS NOT NULL
                      AND JSON_CONTAINS_PATH(`custom_fields`, 'one', '$.custom_preorder_active') = 1
                ");
            } catch (\Throwable) {
                // Tabelle product besitzt in Shopware 6.5+ keine custom_fields Spalte (liegt auf product_translation)
            }

            // 4. Tag "Vorbestellung" und Order-Tag-Verknüpfungen vollständig entfernen
            $connection->executeStatement("
                DELETE ot FROM `order_tag` ot
                INNER JOIN `tag` t ON ot.tag_id = t.id
                WHERE t.name = 'Vorbestellung'
            ");
            $connection->executeStatement("
                DELETE FROM `tag` WHERE name = 'Vorbestellung'
            ");

            // 5. Gespeicherte Plugin-Konfigurationen bereinigen
            $connection->executeStatement("
                DELETE FROM system_config
                WHERE configuration_key LIKE 'CustomPreOrderManager.config.%'
            ");

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
