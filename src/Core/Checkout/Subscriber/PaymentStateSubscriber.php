<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Checkout\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reagiert auf Zahlungsstatus-Änderungen der Order-Transaktion.
 *
 * - paid:      sold_count wird inkrementiert (idempotent mit Set-then-Increment)
 * - cancelled: sold_count wird nur dekrementiert wenn zuvor bezahlt (Quota-Spoofing-Guard)
 * - refunded:  sold_count wird nur dekrementiert wenn zuvor bezahlt (Doppel-Storno-Guard)
 *
 * @see ADR-009, SEC-2026-001, SEC-2026-002
 */
class PaymentStateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'state_enter.order_transaction.state.paid' => ['onPaymentPaid', 10],
            'state_enter.order_transaction.state.cancelled' => ['onPaymentCancelled', 10],
            'state_enter.order_transaction.state.refunded' => ['onPaymentRefunded', 10],
        ];
    }

    public function onPaymentPaid(OrderStateMachineStateChangeEvent $event): void
    {
        $order = $event->getOrder();

        if ($this->isCounterApplied($order->getId())) {
            $this->logger->info('PreOrder: sold_count already applied for order, skipping increment (idempotency)', [
                'orderId' => $order->getId(),
            ]);
            return;
        }

        // Set-then-Increment (Pessimistic Flagging): Flag VOR Inkrement setzen
        $this->setCounterApplied($order->getId(), true);
        $this->adjustSoldCount($event, +1);
    }

    public function onPaymentCancelled(OrderStateMachineStateChangeEvent $event): void
    {
        $this->handlePaymentRevocation($event);
    }

    public function onPaymentRefunded(OrderStateMachineStateChangeEvent $event): void
    {
        $this->handlePaymentRevocation($event);
    }

    private function handlePaymentRevocation(OrderStateMachineStateChangeEvent $event): void
    {
        $order = $event->getOrder();

        if (!$this->isCounterApplied($order->getId())) {
            $this->logger->info('PreOrder: sold_count not applied for order, skipping decrement (quota spoofing guard)', [
                'orderId' => $order->getId(),
            ]);
            return;
        }

        $this->adjustSoldCount($event, -1);
        $this->setCounterApplied($order->getId(), false);
    }

    private function isCounterApplied(string $orderId): bool
    {
        try {
            $applied = $this->connection->fetchOne(
                'SELECT JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, "$.custom_preorder_sold_count_applied"))
                 FROM `order`
                 WHERE `id` = :id AND `version_id` = :versionId',
                [
                    'id' => Uuid::fromHexToBytes($orderId),
                    'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                ],
                [
                    'id' => ParameterType::BINARY,
                    'versionId' => ParameterType::BINARY,
                ]
            );

            return $applied === 'true' || $applied === '1';
        } catch (\Throwable $e) {
            $this->logger->error('PreOrder: Failed to check counter applied status', [
                'orderId' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function setCounterApplied(string $orderId, bool $applied): void
    {
        try {
            $jsonValue = $applied ? 'true' : 'false';
            $this->connection->executeStatement(
                "UPDATE `order`
                 SET `custom_fields` = JSON_SET(
                     COALESCE(`custom_fields`, '{}'),
                     '$.custom_preorder_sold_count_applied',
                     {$jsonValue}
                 )
                 WHERE `id` = :id AND `version_id` = :versionId",
                [
                    'id' => Uuid::fromHexToBytes($orderId),
                    'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                ],
                [
                    'id' => ParameterType::BINARY,
                    'versionId' => ParameterType::BINARY,
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error('PreOrder: Failed to update counter applied flag on order', [
                'orderId' => $orderId,
                'applied' => $applied,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function adjustSoldCount(OrderStateMachineStateChangeEvent $event, int $factor): void
    {
        $order = $event->getOrder();
        $lineItems = $order->getLineItems();

        if (!$lineItems || $lineItems->count() === 0) {
            return;
        }

        $adjustedCount = 0;

        foreach ($lineItems as $item) {
            if (!($item->getPayload()['isPreOrder'] ?? false)) {
                continue;
            }

            $productId = $item->getReferencedId();
            if (!$productId || !Uuid::isValid($productId)) {
                continue;
            }

            $qty = $item->getQuantity();

            if ($factor > 0) {
                $this->incrementSoldCount($productId, $qty);
            } else {
                $this->decrementSoldCount($productId, $qty);
            }

            $adjustedCount++;
        }

        if ($adjustedCount > 0) {
            $action = $factor > 0 ? 'incremented' : 'decremented';
            $this->logger->info('PreOrder: sold_count {action} for {count} product(s)', [
                'action' => $action,
                'count' => $adjustedCount,
                'orderId' => $order->getId(),
            ]);
        }
    }

    private function incrementSoldCount(string $productId, int $qty): void
    {
        try {
            $this->connection->executeStatement(
                'UPDATE `product_translation`
                 SET `custom_fields` = JSON_SET(
                     COALESCE(`custom_fields`, "{}"),
                     "$.custom_preorder_sold_count",
                     COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, "$.custom_preorder_sold_count")), 0) + :qty
                 )
                 WHERE `product_id` = :id AND `product_version_id` = :versionId',
                [
                    'id' => Uuid::fromHexToBytes($productId),
                    'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    'qty' => $qty,
                ],
                [
                    'id' => ParameterType::BINARY,
                    'versionId' => ParameterType::BINARY,
                    'qty' => ParameterType::INTEGER,
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error('PreOrder: Failed to increment sold_count for product', [
                'productId' => $productId,
                'qty' => $qty,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function decrementSoldCount(string $productId, int $qty): void
    {
        try {
            $this->connection->executeStatement(
                'UPDATE `product_translation`
                 SET `custom_fields` = JSON_SET(
                     COALESCE(`custom_fields`, "{}"),
                     "$.custom_preorder_sold_count",
                     GREATEST(
                         COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, "$.custom_preorder_sold_count")), 0) - :qty,
                         0
                     )
                 )
                 WHERE `product_id` = :id AND `product_version_id` = :versionId',
                [
                    'id' => Uuid::fromHexToBytes($productId),
                    'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    'qty' => $qty,
                ],
                [
                    'id' => ParameterType::BINARY,
                    'versionId' => ParameterType::BINARY,
                    'qty' => ParameterType::INTEGER,
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error('PreOrder: Failed to decrement sold_count for product', [
                'productId' => $productId,
                'qty' => $qty,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
