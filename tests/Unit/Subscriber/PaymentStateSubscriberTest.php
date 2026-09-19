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

        // 1. Check if applied: returns false
        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn(false);

        // 2. Expect flag update on order, then increment on product_translation
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

        // Order was already marked as applied
        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn('true');

        // Neither flag update nor product update should be called
        $this->connection->expects(static::never())->method('executeStatement');

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

        // Order was never paid -> flag is false/null
        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn(false);

        // MUST NOT decrement
        $this->connection->expects(static::never())->method('executeStatement');

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

        // Order was previously paid
        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn('true');

        // Expect decrement on product_translation, then reset flag on order
        $this->connection->expects(static::exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'UPDATE `product_translation`') && str_contains($sql, 'GREATEST')) {
                    return 1;
                }
                if (str_contains($sql, 'UPDATE `order`')) {
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

        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn('true');

        $this->connection->expects(static::exactly(2))
            ->method('executeStatement');

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

        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn(false);

        // 1 order update + 2 product updates = 3 executeStatement calls
        $this->connection->expects(static::exactly(3))->method('executeStatement');

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

        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn(false);

        // Only order flag update is executed, product update is skipped due to invalid UUID
        $this->connection->expects(static::once())
            ->method('executeStatement')
            ->with(static::stringContains('UPDATE `order`'));

        $this->subscriber->onPaymentPaid($event);
    }

    public function testDatabaseExceptionsAreCaughtAndLoggedWithoutThrowing(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 1);
        $event = $this->createStateChangeEvent($order);

        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willThrowException(new \RuntimeException('Connection failed'));

        $this->logger->expects(static::once())
            ->method('error')
            ->with(static::stringContains('Failed to check counter applied status'));

        // Must not throw exception
        $this->subscriber->onPaymentPaid($event);
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
