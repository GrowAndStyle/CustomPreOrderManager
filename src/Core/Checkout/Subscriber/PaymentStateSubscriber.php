<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Checkout\Subscriber;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reagiert auf Zahlungsstatus-Änderungen der Order-Transaktion.
 *
 * - paid:      sold_count wird inkrementiert
 * - cancelled: sold_count wird dekrementiert (GREATEST-Guard gegen negative Werte)
 * - refunded:  sold_count wird dekrementiert (GREATEST-Guard gegen negative Werte)
 *
 * @see ADR-009
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
            'state_enter.order_transaction.state.paid' => 'onPaymentPaid',
            'state_enter.order_transaction.state.cancelled' => 'onPaymentCancelled',
            'state_enter.order_transaction.state.refunded' => 'onPaymentRefunded',
        ];
    }

    public function onPaymentPaid(OrderStateMachineStateChangeEvent $event): void
    {
        $this->adjustSoldCount($event, +1);
    }

    public function onPaymentCancelled(OrderStateMachineStateChangeEvent $event): void
    {
        $this->adjustSoldCount($event, -1);
    }

    public function onPaymentRefunded(OrderStateMachineStateChangeEvent $event): void
    {
        $this->adjustSoldCount($event, -1);
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
            if (!$productId) {
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
            ]
        );
    }

    private function decrementSoldCount(string $productId, int $qty): void
    {
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
            ]
        );
    }
}
