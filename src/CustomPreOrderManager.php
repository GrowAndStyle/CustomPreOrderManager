<?php declare(strict_types=1);

namespace CustomPreOrderManager;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class CustomPreOrderManager extends Plugin
{
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

        // 1. CustomField-Set und zugehörige Felder/Relationen löschen
        $connection->executeStatement("
            DELETE cf, cfsr, cfs
            FROM custom_field_set cfs
            LEFT JOIN custom_field_set_relation cfsr ON cfsr.set_id = cfs.id
            LEFT JOIN custom_field cf ON cf.set_id = cfs.id
            WHERE cfs.name = 'custom_preorder_set'
        ");

        // 2. Custom-Fields an allen Produkten entfernen
        $connection->executeStatement("
            UPDATE `product`
            SET `custom_fields` = JSON_REMOVE(
                `custom_fields`,
                '$.custom_preorder_active',
                '$.custom_preorder_release_date',
                '$.custom_preorder_release_text',
                '$.custom_preorder_inbound_stock',
                '$.custom_preorder_sold_count'
            )
            WHERE `custom_fields` IS NOT NULL
        ");

        // 3. Custom-Fields an allen Produkt-Übersetzungen entfernen
        $connection->executeStatement("
            UPDATE `product_translation`
            SET `custom_fields` = JSON_REMOVE(
                `custom_fields`,
                '$.custom_preorder_active',
                '$.custom_preorder_release_date',
                '$.custom_preorder_release_text',
                '$.custom_preorder_inbound_stock',
                '$.custom_preorder_sold_count'
            )
            WHERE `custom_fields` IS NOT NULL
        ");

        // 4. Gespeicherte Plugin-Konfigurationen bereinigen
        $connection->executeStatement("
            DELETE FROM system_config
            WHERE configuration_key LIKE 'CustomPreOrderManager.config.%'
        ");
    }
}
