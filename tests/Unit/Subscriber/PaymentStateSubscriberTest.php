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
 * Verifiziert nach Enterprise Tier-1 Standard (ADR-009):
 * - Korrekte Event-Abonnements (paid/cancelled/refunded)
 * - Inkrement bei Zahlung, Dekrement bei Storno/Refund
 * - GREATEST-Guard gegen negative Werte
 * - Filterung: Nur isPreOrder-LineItems, gemischte Warenkörbe
 * - Edge Cases: null LineItems, null ReferencedId, leere Order
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

        static::assertSame('onPaymentPaid', $events['state_enter.order_transaction.state.paid']);
        static::assertSame('onPaymentCancelled', $events['state_enter.order_transaction.state.cancelled']);
        static::assertSame('onPaymentRefunded', $events['state_enter.order_transaction.state.refunded']);
    }

    public function testOnPaymentPaidIncrementsCounterForPreOrderItems(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 3);
        $event = $this->createStateChangeEvent($order);

        $this->connection->expects(static::once())
            ->method('executeStatement')
            ->with(
                static::stringContains('UPDATE `product_translation`'),
                static::callback(function (array $params) use ($productId) {
                    return $params['qty'] === 3
                        && $params['id'] === Uuid::fromHexToBytes($productId);
                })
            );

        $this->logger->expects(static::once())->method('info');

        $this->subscriber->onPaymentPaid($event);
    }

    public function testOnPaymentPaidSkipsNonPreOrderItems(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $normalItem = new OrderLineItemEntity();
        $normalItem->setId(Uuid::randomHex());
        $normalItem->setReferencedId(Uuid::randomHex());
        $normalItem->setQuantity(2);
        $normalItem->setPayload(['isPreOrder' => false]);

        $order->setLineItems(new OrderLineItemCollection([$normalItem]));

        $event = $this->createStateChangeEvent($order);

        $this->connection->expects(static::never())->method('executeStatement');
        $this->logger->expects(static::never())->method('info');

        $this->subscriber->onPaymentPaid($event);
    }

    public function testOnPaymentPaidSkipsOrderWithoutLineItems(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $event = $this->createStateChangeEvent($order);

        $this->connection->expects(static::never())->method('executeStatement');

        $this->subscriber->onPaymentPaid($event);
    }

    public function testOnPaymentCancelledDecrementsCounter(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 2);
        $event = $this->createStateChangeEvent($order);

        $this->connection->expects(static::once())
            ->method('executeStatement')
            ->with(
                static::logicalAnd(
                    static::stringContains('UPDATE `product_translation`'),
                    static::stringContains('GREATEST')
                ),
                static::callback(function (array $params) use ($productId) {
                    return $params['qty'] === 2
                        && $params['id'] === Uuid::fromHexToBytes($productId);
                })
            );

        $this->logger->expects(static::once())->method('info');

        $this->subscriber->onPaymentCancelled($event);
    }

    public function testOnPaymentRefundedDecrementsCounter(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrderWithPreOrderItem($productId, 1);
        $event = $this->createStateChangeEvent($order);

        $this->connection->expects(static::once())
            ->method('executeStatement')
            ->with(
                static::logicalAnd(
                    static::stringContains('UPDATE `product_translation`'),
                    static::stringContains('GREATEST')
                ),
                static::callback(function (array $params) {
                    return $params['qty'] === 1;
                })
            );

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

        $this->connection->expects(static::exactly(2))->method('executeStatement');

        $this->subscriber->onPaymentPaid($event);
    }

    public function testOnPaymentPaidWithMixedCart(): void
    {
        $preOrderProductId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $preOrderItem = new OrderLineItemEntity();
        $preOrderItem->setId(Uuid::randomHex());
        $preOrderItem->setReferencedId($preOrderProductId);
        $preOrderItem->setQuantity(1);
        $preOrderItem->setPayload(['isPreOrder' => true]);

        $normalItem = new OrderLineItemEntity();
        $normalItem->setId(Uuid::randomHex());
        $normalItem->setReferencedId(Uuid::randomHex());
        $normalItem->setQuantity(3);
        $normalItem->setPayload(['isPreOrder' => false]);

        $order->setLineItems(new OrderLineItemCollection([$preOrderItem, $normalItem]));

        $event = $this->createStateChangeEvent($order);

        $this->connection->expects(static::once())
            ->method('executeStatement')
            ->with(
                static::stringContains('UPDATE `product_translation`'),
                static::callback(function (array $params) use ($preOrderProductId) {
                    return $params['qty'] === 1
                        && $params['id'] === Uuid::fromHexToBytes($preOrderProductId);
                })
            );

        $this->subscriber->onPaymentPaid($event);
    }

    public function testOnPaymentPaidSkipsItemsWithNullReferencedId(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $preOrderItem = new OrderLineItemEntity();
        $preOrderItem->setId(Uuid::randomHex());
        $preOrderItem->setReferencedId(null);
        $preOrderItem->setQuantity(1);
        $preOrderItem->setPayload(['isPreOrder' => true]);

        $order->setLineItems(new OrderLineItemCollection([$preOrderItem]));

        $event = $this->createStateChangeEvent($order);

        $this->connection->expects(static::never())->method('executeStatement');

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
