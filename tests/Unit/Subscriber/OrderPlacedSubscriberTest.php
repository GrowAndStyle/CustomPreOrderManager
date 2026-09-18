<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Subscriber;

use CustomPreOrderManager\Core\Checkout\Event\PreOrderPlacedEvent;
use CustomPreOrderManager\Core\Checkout\Subscriber\OrderPlacedSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderPlacedSubscriberTest extends TestCase
{
    private EntityRepository $orderRepository;
    private EntityRepository $tagRepository;
    private EventDispatcherInterface $dispatcher;
    private LoggerInterface $logger;
    private OrderPlacedSubscriber $subscriber;
    private Context $context;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(EntityRepository::class);
        $this->tagRepository = $this->createMock(EntityRepository::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->context = Context::createDefaultContext();

        $this->subscriber = new OrderPlacedSubscriber(
            $this->orderRepository,
            $this->tagRepository,
            $this->dispatcher,
            $this->logger,
        );
    }

    public function testSubscribedEvents(): void
    {
        $events = OrderPlacedSubscriber::getSubscribedEvents();
        static::assertArrayHasKey(CheckoutOrderPlacedEvent::class, $events);
        static::assertSame('onOrderPlaced', $events[CheckoutOrderPlacedEvent::class]);
    }

    public function testOnOrderPlacedReturnsEarlyWhenLineItemsNull(): void
    {
        $order = $this->createMock(OrderEntity::class);
        $order->method('getLineItems')->willReturn(null);

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        $this->tagRepository->expects(static::never())->method('searchIds');
        $this->orderRepository->expects(static::never())->method('update');
        $this->dispatcher->expects(static::never())->method('dispatch');

        $this->subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedReturnsEarlyWhenNoPreOrderItems(): void
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

        $this->tagRepository->expects(static::never())->method('searchIds');
        $this->orderRepository->expects(static::never())->method('update');
        $this->dispatcher->expects(static::never())->method('dispatch');

        $this->subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedTagsOrderWithExistingTag(): void
    {
        $orderId = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);

        $preOrderItem = new OrderLineItemEntity();
        $preOrderItem->setId(Uuid::randomHex());
        $preOrderItem->setReferencedId(Uuid::randomHex());
        $preOrderItem->setQuantity(3);
        $preOrderItem->setPayload(['isPreOrder' => true]);

        $order->setLineItems(new OrderLineItemCollection([$preOrderItem]));

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        // Tag existiert bereits
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn($tagId);
        $this->tagRepository->method('searchIds')->willReturn($idSearchResult);
        $this->tagRepository->expects(static::never())->method('create');

        // Tag an Order verknüpfen
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

        // Event wird ausgelöst
        $this->dispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PreOrderPlacedEvent::class), PreOrderPlacedEvent::EVENT_NAME);

        // Logger wird aufgerufen
        $this->logger->expects(static::once())->method('info');

        $this->subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedCreatesTagWhenNotExists(): void
    {
        $orderId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);

        $preOrderItem = new OrderLineItemEntity();
        $preOrderItem->setId(Uuid::randomHex());
        $preOrderItem->setReferencedId(Uuid::randomHex());
        $preOrderItem->setQuantity(1);
        $preOrderItem->setPayload(['isPreOrder' => true]);

        $order->setLineItems(new OrderLineItemCollection([$preOrderItem]));

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

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

        // Event wird ausgelöst
        $this->dispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PreOrderPlacedEvent::class), PreOrderPlacedEvent::EVENT_NAME);

        $this->logger->expects(static::once())->method('info');

        $this->subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedHandlesMultiplePreOrderItems(): void
    {
        $orderId = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);

        $itemA = new OrderLineItemEntity();
        $itemA->setId(Uuid::randomHex());
        $itemA->setReferencedId(Uuid::randomHex());
        $itemA->setQuantity(2);
        $itemA->setPayload(['isPreOrder' => true]);

        $itemB = new OrderLineItemEntity();
        $itemB->setId(Uuid::randomHex());
        $itemB->setReferencedId(Uuid::randomHex());
        $itemB->setQuantity(5);
        $itemB->setPayload(['isPreOrder' => true]);

        $order->setLineItems(new OrderLineItemCollection([$itemA, $itemB]));

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn($tagId);
        $this->tagRepository->method('searchIds')->willReturn($idSearchResult);

        $this->orderRepository->expects(static::once())->method('update');

        // Event mit preOrderItemCount = 2
        $this->dispatcher->expects(static::once())
            ->method('dispatch')
            ->with(
                static::callback(function (PreOrderPlacedEvent $event) {
                    return $event->getPreOrderItemCount() === 2;
                }),
                PreOrderPlacedEvent::EVENT_NAME
            );

        $this->logger->expects(static::once())->method('info');

        $this->subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedCountsOnlyPreOrderItems(): void
    {
        $orderId = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);

        $preOrderItem = new OrderLineItemEntity();
        $preOrderItem->setId(Uuid::randomHex());
        $preOrderItem->setReferencedId(Uuid::randomHex());
        $preOrderItem->setQuantity(1);
        $preOrderItem->setPayload(['isPreOrder' => true]);

        $normalItem = new OrderLineItemEntity();
        $normalItem->setId(Uuid::randomHex());
        $normalItem->setReferencedId(Uuid::randomHex());
        $normalItem->setQuantity(3);
        $normalItem->setPayload(['isPreOrder' => false]);

        $order->setLineItems(new OrderLineItemCollection([$preOrderItem, $normalItem]));

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn($tagId);
        $this->tagRepository->method('searchIds')->willReturn($idSearchResult);

        $this->orderRepository->expects(static::once())->method('update');

        // Event mit preOrderItemCount = 1 (nur die eine Vorbestellposition)
        $this->dispatcher->expects(static::once())
            ->method('dispatch')
            ->with(
                static::callback(function (PreOrderPlacedEvent $event) {
                    return $event->getPreOrderItemCount() === 1;
                }),
                PreOrderPlacedEvent::EVENT_NAME
            );

        $this->subscriber->onOrderPlaced($event);
    }
}
