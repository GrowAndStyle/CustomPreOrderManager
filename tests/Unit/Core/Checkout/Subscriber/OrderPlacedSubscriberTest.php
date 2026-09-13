<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Core\Checkout\Subscriber;

use CustomPreOrderManager\Core\Checkout\Subscriber\OrderPlacedSubscriber;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

class OrderPlacedSubscriberTest extends TestCase
{
    private EntityRepository $orderRepository;
    private EntityRepository $tagRepository;
    private Connection $connection;
    private OrderPlacedSubscriber $subscriber;
    private Context $context;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(EntityRepository::class);
        $this->tagRepository = $this->createMock(EntityRepository::class);
        $this->connection = $this->createMock(Connection::class);
        $this->context = Context::createDefaultContext();

        $this->subscriber = new OrderPlacedSubscriber(
            $this->orderRepository,
            $this->tagRepository,
            $this->connection
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = OrderPlacedSubscriber::getSubscribedEvents();
        static::assertArrayHasKey(CheckoutOrderPlacedEvent::class, $events);
        static::assertSame('onOrderPlaced', $events[CheckoutOrderPlacedEvent::class]);
    }

    public function testOnOrderPlacedReturnsEarlyWhenLineItemsNull(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        $this->connection->expects(static::never())->method('executeStatement');
        $this->tagRepository->expects(static::never())->method('searchIds');
        $this->orderRepository->expects(static::never())->method('update');

        $this->subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedReturnsEarlyWhenLineItemsEmpty(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setLineItems(new OrderLineItemCollection());

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        $this->connection->expects(static::never())->method('executeStatement');
        $this->tagRepository->expects(static::never())->method('searchIds');
        $this->orderRepository->expects(static::never())->method('update');

        $this->subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedReturnsEarlyWhenNoPreOrderItemInOrder(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $normalItem = new OrderLineItemEntity();
        $normalItem->setId(Uuid::randomHex());
        $normalItem->setReferencedId(Uuid::randomHex());
        $normalItem->setQuantity(1);
        $normalItem->setPayload(['isPreOrder' => false]);

        $order->setLineItems(new OrderLineItemCollection([$normalItem]));

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        $this->connection->expects(static::never())->method('executeStatement');
        $this->tagRepository->expects(static::never())->method('searchIds');
        $this->orderRepository->expects(static::never())->method('update');

        $this->subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedTagsOrderAndIncrementsStockWithExistingTag(): void
    {
        $orderId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);

        $preOrderItem = new OrderLineItemEntity();
        $preOrderItem->setId(Uuid::randomHex());
        $preOrderItem->setReferencedId($productId);
        $preOrderItem->setQuantity(3);
        $preOrderItem->setPayload(['isPreOrder' => true]);

        $order->setLineItems(new OrderLineItemCollection([$preOrderItem]));

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        // 1. DBAL Counter Increment
        $this->connection->expects(static::once())
            ->method('executeStatement')
            ->with(
                static::stringContains('UPDATE `product`'),
                [
                    'id' => Uuid::fromHexToBytes($productId),
                    'qty' => 3,
                ]
            );

        // 2. Tag finden (existiert bereits)
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn($tagId);
        $this->tagRepository->method('searchIds')->willReturn($idSearchResult);
        $this->tagRepository->expects(static::never())->method('create');

        // 3. Tag an Order verknüpfen
        $this->orderRepository->expects(static::once())
            ->method('update')
            ->with(
                [
                    [
                        'id' => $orderId,
                        'tags' => [
                            ['id' => $tagId],
                        ],
                    ],
                ],
                $this->context
            );

        $this->subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedCreatesTagWhenNotExists(): void
    {
        $orderId = Uuid::randomHex();
        $productId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);

        $preOrderItem = new OrderLineItemEntity();
        $preOrderItem->setId(Uuid::randomHex());
        $preOrderItem->setReferencedId($productId);
        $preOrderItem->setQuantity(1);
        $preOrderItem->setPayload(['isPreOrder' => true]);

        $order->setLineItems(new OrderLineItemCollection([$preOrderItem]));

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        $this->connection->expects(static::once())->method('executeStatement');

        // Tag existiert noch nicht
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn(null);
        $this->tagRepository->method('searchIds')->willReturn($idSearchResult);

        // Neuer Tag wird erstellt
        $this->tagRepository->expects(static::once())
            ->method('create')
            ->with(
                static::callback(function (array $data) {
                    return ($data[0]['name'] ?? null) === 'Vorbestellung'
                        && !empty($data[0]['id']);
                }),
                $this->context
            );

        // Order wird getaggt
        $this->orderRepository->expects(static::once())->method('update');

        $this->subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedSkipsDbalWhenReferencedIdInvalidOrNull(): void
    {
        $orderId = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);

        $preOrderItem = new OrderLineItemEntity();
        $preOrderItem->setId(Uuid::randomHex());
        $preOrderItem->setReferencedId(null); // kein gültiges Produkt
        $preOrderItem->setQuantity(1);
        $preOrderItem->setPayload(['isPreOrder' => true]);

        $order->setLineItems(new OrderLineItemCollection([$preOrderItem]));

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        // Keine DBAL Counter-Ausführung da kein Product-ID
        $this->connection->expects(static::never())->method('executeStatement');

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn($tagId);
        $this->tagRepository->method('searchIds')->willReturn($idSearchResult);

        $this->orderRepository->expects(static::once())->method('update');

        $this->subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedHandlesMultiplePreOrderItems(): void
    {
        $orderId = Uuid::randomHex();
        $productA = Uuid::randomHex();
        $productB = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);

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

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        // 2 DBAL Updates für 2 unterschiedliche Produkte
        $this->connection->expects(static::exactly(2))->method('executeStatement');

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn($tagId);
        $this->tagRepository->method('searchIds')->willReturn($idSearchResult);

        $this->orderRepository->expects(static::once())->method('update');

        $this->subscriber->onOrderPlaced($event);
    }
}
