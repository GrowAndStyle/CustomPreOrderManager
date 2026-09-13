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
    public function install(InstallContext $context): void
    {
        // Migrationen laufen automatisch
    }

    public function update(UpdateContext $context): void
    {
        // Schema-Updates über Migrationen gesteuert
    }

    public function activate(ActivateContext $context): void
    {
        // Aktivierungslogik
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
        $connection->executeStatement("DROP TABLE IF EXISTS `custom_preorder_waitlist`");

        // 2. CustomFieldSets entfernen
        $connection->executeStatement(
            "DELETE FROM `custom_field_set` WHERE `name` = 'custom_preorder_set'"
        );

        // 3. System-Config entfernen
        $connection->executeStatement(
            "DELETE FROM `system_config` WHERE `configuration_key` LIKE 'CustomPreOrderManager.config.%'"
        );
    }
}
