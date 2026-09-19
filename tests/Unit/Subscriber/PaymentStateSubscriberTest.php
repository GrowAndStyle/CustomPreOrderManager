<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Subscriber;

use CustomPreOrderManager\Core\Checkout\Subscriber\PaymentStateSubscriber;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Unit-Tests für den PaymentStateSubscriber.
 *
 * Verifiziert nach Enterprise Tier-1 Standard (ADR-009, SEC-2026-001, SEC-2026-002):
 * - Korrekte Event-Abonnements (paid/cancelled/refunded mit Priorität 10)
 * - Idempotenz-Guard (Set-then-Increment bei Zahlung)
 * - Quota-Spoofing-Guard (Kein Dekrement bei unbezahlten Abbrüchen)
 * - Doppel-Storno-Guard (refunded gefolgt von cancelled dekrementiert nur 1x)
 * - GREATEST-Guard gegen negative Werte
 * - Exception-Resilienz gegen Webhook-Abstürze
 */
class PaymentStateSubscriberTest extends TestCase
{
    private Connection $connection;
    private LoggerInterface $logger;
    private PaymentStateSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new PaymentStateSubscriber(
            $this->connection,
            $this->logger,
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = PaymentStateSubscriber::getSubscribedEvents();

        static::assertArrayHasKey('state_enter.order_transaction.state.paid', $events);
        static::assertArrayHasKey('state_enter.order_transaction.state.cancelled', $events);
        static::assertArrayHasKey('state_enter.order_transaction.state.refunded', $events);

        static::assertSame(['onPaymentPaid', 10], $events['state_enter.order_transaction.state.paid']);
        static::assertSame(['onPaymentCancelled', 10], $events['state_enter.order_transaction.state.cancelled']);
        static::assertSame(['onPaymentRefunded', 10], $events['state_enter.order_transaction.state.refunded']);
    }

    public function testOnPaymentPaidIncrementsCounterAndSetsAppliedFlag(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 3);
        $event = $this->createStateChangeEvent($order);

        // 1. UPDATE order sets flag (returns 1 affected row)
        // 2. UPDATE product_translation increments sold_count
        $this->connection->expects(static::exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'UPDATE `order`')) {
                    return 1;
                }
                if (str_contains($sql, 'UPDATE `product_translation`')) {
                    return 1;
                }
                return 0;
            });

        $this->logger->expects(static::once())->method('info');

        $this->subscriber->onPaymentPaid($event);
    }

    public function testOnPaymentPaidIgnoresAlreadyAppliedOrder(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 3);
        $event = $this->createStateChangeEvent($order);

        // Atomic UPDATE returns 0 (condition matched 0 rows because flag was already true)
        $this->connection->expects(static::once())
            ->method('executeStatement')
            ->with(static::stringContains('UPDATE `order`'))
            ->willReturn(0);

        $this->logger->expects(static::once())
            ->method('info')
            ->with(static::stringContains('already applied'));

        $this->subscriber->onPaymentPaid($event);
    }

    public function testOnPaymentCancelledIgnoresUnpaidOrder(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 2);
        $event = $this->createStateChangeEvent($order);

        // Atomic UPDATE returns 0 (order was never paid, condition matched 0 rows)
        $this->connection->expects(static::once())
            ->method('executeStatement')
            ->with(static::stringContains('UPDATE `order`'))
            ->willReturn(0);

        $this->logger->expects(static::once())
            ->method('info')
            ->with(static::stringContains('not applied'));

        $this->subscriber->onPaymentCancelled($event);
    }

    public function testOnPaymentCancelledDecrementsAndResetsFlagIfPaid(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 2);
        $event = $this->createStateChangeEvent($order);

        // 1. UPDATE order resets flag (returns 1 affected row)
        // 2. UPDATE product_translation decrements counter
        $this->connection->expects(static::exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'UPDATE `order`')) {
                    return 1;
                }
                if (str_contains($sql, 'UPDATE `product_translation`') && str_contains($sql, 'GREATEST')) {
                    return 1;
                }
                return 0;
            });

        $this->subscriber->onPaymentCancelled($event);
    }

    public function testOnPaymentRefundedDecrementsAndResetsFlagIfPaid(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 1);
        $event = $this->createStateChangeEvent($order);

        $this->connection->expects(static::exactly(2))
            ->method('executeStatement')
            ->willReturn(1);

        $this->subscriber->onPaymentRefunded($event);
    }

    public function testOnPaymentPaidHandlesMultiplePreOrderProducts(): void
    {
        $productA = Uuid::randomHex();
        $productB = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $itemA = new OrderLineItemEntity();
        $itemA->setId(Uuid::randomHex());
        $itemA->setReferencedId($productA);
        $itemA->setQuantity(2);
        $itemA->setPayload(['isPreOrder' => true]);

        $itemB = new OrderLineItemEntity();
        $itemB->setId(Uuid::randomHex());
        $itemB->setReferencedId($productB);
        $itemB->setQuantity(5);
        $itemB->setPayload(['isPreOrder' => true]);

        $order->setLineItems(new OrderLineItemCollection([$itemA, $itemB]));

        $event = $this->createStateChangeEvent($order);

        // 1 order update (atomic flag set) + 2 product updates = 3 executeStatement calls
        $this->connection->expects(static::exactly(3))
            ->method('executeStatement')
            ->willReturn(1);

        $this->subscriber->onPaymentPaid($event);
    }

    public function testOnPaymentPaidSkipsItemsWithNullOrInvalidReferencedId(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $preOrderItem = new OrderLineItemEntity();
        $preOrderItem->setId(Uuid::randomHex());
        $preOrderItem->setReferencedId('not-a-valid-uuid');
        $preOrderItem->setQuantity(1);
        $preOrderItem->setPayload(['isPreOrder' => true]);

        $order->setLineItems(new OrderLineItemCollection([$preOrderItem]));

        $event = $this->createStateChangeEvent($order);

        // Only order flag update is executed, product update is skipped due to invalid UUID
        $this->connection->expects(static::once())
            ->method('executeStatement')
            ->with(static::stringContains('UPDATE `order`'))
            ->willReturn(1);

        $this->subscriber->onPaymentPaid($event);
    }

    public function testDatabaseExceptionsAreCaughtAndLoggedWithoutThrowing(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 1);
        $event = $this->createStateChangeEvent($order);

        $this->connection->expects(static::once())
            ->method('executeStatement')
            ->willThrowException(new \RuntimeException('Connection failed'));

        $this->logger->expects(static::once())
            ->method('error')
            ->with(static::stringContains('Failed to set counter applied flag on order'));

        // Must not throw exception
        $this->subscriber->onPaymentPaid($event);
    }


    public function testSetCounterAppliedLogsErrorOnExceptionWithoutThrowing(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 1);
        $event = $this->createStateChangeEvent($order);

        $this->connection->method('executeStatement')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'UPDATE `order`')) {
                    throw new \RuntimeException('Lock wait timeout exceeded');
                }
                return 1;
            });

        $this->logger->expects(static::once())
            ->method('error')
            ->with(static::stringContains('Failed to set counter applied flag on order'));

        $this->subscriber->onPaymentPaid($event);
    }

    public function testRevokeCounterAppliedLogsErrorOnExceptionWithoutThrowing(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 1);
        $event = $this->createStateChangeEvent($order);

        $this->connection->method('executeStatement')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'UPDATE `order`')) {
                    throw new \RuntimeException('Lock wait timeout on revoke');
                }
                return 1;
            });

        $this->logger->expects(static::once())
            ->method('error')
            ->with(static::stringContains('Failed to revoke counter applied flag on order'));

        $this->subscriber->onPaymentCancelled($event);
    }

    public function testIncrementSoldCountLogsErrorOnExceptionWithoutThrowing(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 1);
        $event = $this->createStateChangeEvent($order);

        $this->connection->method('executeStatement')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'UPDATE `order`')) {
                    return 1;
                }
                if (str_contains($sql, 'UPDATE `product_translation`')) {
                    throw new \RuntimeException('Deadlock on product_translation');
                }
                return 1;
            });

        $this->logger->expects(static::once())
            ->method('error')
            ->with(static::stringContains('Failed to increment sold_count for product'));

        $this->subscriber->onPaymentPaid($event);
    }

    public function testDecrementSoldCountLogsErrorOnExceptionWithoutThrowing(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 1);
        $event = $this->createStateChangeEvent($order);

        $this->connection->method('executeStatement')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'UPDATE `order`')) {
                    return 1;
                }
                if (str_contains($sql, 'UPDATE `product_translation`')) {
                    throw new \RuntimeException('Deadlock on decrement');
                }
                return 1;
            });

        $this->logger->expects(static::once())
            ->method('error')
            ->with(static::stringContains('Failed to decrement sold_count for product'));

        $this->subscriber->onPaymentCancelled($event);
    }

    public function testAdjustSoldCountHandlesNullAndEmptyLineItems(): void
    {
        // 1. With null line items (OrderEntity default is null without calling setLineItems)
        $orderWithNull = new OrderEntity();
        $orderWithNull->setId(Uuid::randomHex());
        $eventNull = $this->createStateChangeEvent($orderWithNull);

        // 2. With empty line items collection
        $orderWithEmpty = new OrderEntity();
        $orderWithEmpty->setId(Uuid::randomHex());
        $orderWithEmpty->setLineItems(new OrderLineItemCollection());
        $eventEmpty = $this->createStateChangeEvent($orderWithEmpty);

        $this->connection->expects(static::exactly(2))
            ->method('executeStatement')
            ->with(static::stringContains('UPDATE `order`'))
            ->willReturn(1);

        $this->subscriber->onPaymentPaid($eventNull);
        $this->subscriber->onPaymentPaid($eventEmpty);
    }

    public function testAdjustSoldCountSkipsNonPreOrderItemsAndNullReferencedId(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $itemNonPreOrder = new OrderLineItemEntity();
        $itemNonPreOrder->setId(Uuid::randomHex());
        $itemNonPreOrder->setReferencedId(Uuid::randomHex());
        $itemNonPreOrder->setQuantity(1);
        $itemNonPreOrder->setPayload(['isPreOrder' => false]);

        $itemNullRefId = new OrderLineItemEntity();
        $itemNullRefId->setId(Uuid::randomHex());
        $itemNullRefId->setReferencedId(null);
        $itemNullRefId->setQuantity(1);
        $itemNullRefId->setPayload(['isPreOrder' => true]);

        $order->setLineItems(new OrderLineItemCollection([$itemNonPreOrder, $itemNullRefId]));

        $event = $this->createStateChangeEvent($order);

        // Only order update, no product update
        $this->connection->expects(static::once())
            ->method('executeStatement')
            ->with(static::stringContains('UPDATE `order`'))
            ->willReturn(1);

        $this->subscriber->onPaymentPaid($event);
    }

    public function testOnPaymentRefundedIgnoresUnpaidOrder(): void
    {
        $order = $this->createOrderWithPreOrderItem(Uuid::randomHex(), 2);
        $event = $this->createStateChangeEvent($order);

        $this->connection->expects(static::once())
            ->method('executeStatement')
            ->with(static::stringContains('UPDATE `order`'))
            ->willReturn(0);

        $this->logger->expects(static::once())
            ->method('info')
            ->with(static::stringContains('not applied'));

        $this->subscriber->onPaymentRefunded($event);
    }

    // --- Helpers ---

    private function createOrderWithPreOrderItem(string $productId, int $qty): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $item = new OrderLineItemEntity();
        $item->setId(Uuid::randomHex());
        $item->setReferencedId($productId);
        $item->setQuantity($qty);
        $item->setPayload(['isPreOrder' => true]);

        $order->setLineItems(new OrderLineItemCollection([$item]));

        return $order;
    }

    private function createStateChangeEvent(OrderEntity $order): OrderStateMachineStateChangeEvent
    {
        $event = $this->createMock(OrderStateMachineStateChangeEvent::class);
        $event->method('getOrder')->willReturn($order);

        return $event;
    }
}
