<?php declare(strict_types=1);

namespace CustomPreOrderManager\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1726200100CreatePreOrderWaitlist extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1726200100;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `custom_preorder_waitlist` (
                `id`                 BINARY(16)   NOT NULL,
                `product_id`         BINARY(16)   NOT NULL,
                `product_version_id` BINARY(16)   NOT NULL,
                `sales_channel_id`   BINARY(16)   NOT NULL,
                `email`              VARCHAR(255) NOT NULL,
                `name`               VARCHAR(255) NULL,
                `status`             VARCHAR(32)  NOT NULL DEFAULT \'pending_doi\',
                `token`              VARCHAR(64)  NULL,
                `confirmed_at`       DATETIME(3)  NULL,
                `notified_at`        DATETIME(3)  NULL,
                `created_at`         DATETIME(3)  NOT NULL,
                `updated_at`         DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                KEY `idx.preorder_waitlist.product` (`product_id`, `product_version_id`),
                KEY `idx.preorder_waitlist.status` (`status`),
                KEY `idx.preorder_waitlist.token` (`token`),
                CONSTRAINT `fk.preorder_waitlist.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.preorder_waitlist.sales_channel`
                    FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
