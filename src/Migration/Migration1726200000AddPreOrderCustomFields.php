<?php declare(strict_types=1);

namespace CustomPreOrderManager\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1726200000AddPreOrderCustomFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1726200000;
    }

    public function update(Connection $connection): void
    {
        // CustomFieldSets werden gemäß Shopware 6.5 Standard und CIS-Architektur
        // im Plugin-Lifecycle (install/activate) über das DAL-Repository custom_field_set.repository verwaltet.
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
