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
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderPlacedSubscriberTest extends TestCase
{
    private StaticTestEntityRepository $orderRepository;
    private StaticTestEntityRepository $tagRepository;
    private EventDispatcherInterface $dispatcher;
    private LoggerInterface $logger;
    private OrderPlacedSubscriber $subscriber;
    private Context $context;

    protected function setUp(): void
    {
        $this->orderRepository = new StaticTestEntityRepository();
        $this->tagRepository = new StaticTestEntityRepository();
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
        static::assertSame(['onOrderPlaced', 100], $events[CheckoutOrderPlacedEvent::class]);
    }

    public function testOnOrderPlacedReturnsEarlyWhenLineItemsNull(): void
    {
        $order = $this->createMock(OrderEntity::class);
        $order->method('getLineItems')->willReturn(null);

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        $this->subscriber->onOrderPlaced($event);

        static::assertSame(0, $this->tagRepository->searchIdsCalls);
        static::assertSame(0, $this->tagRepository->createCalls);
        static::assertSame(0, $this->orderRepository->updateCalls);
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

        $this->subscriber->onOrderPlaced($event);

        static::assertSame(0, $this->tagRepository->searchIdsCalls);
        static::assertSame(0, $this->tagRepository->createCalls);
        static::assertSame(0, $this->orderRepository->updateCalls);
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
        $this->tagRepository->searchIdsCallback = static fn() => $idSearchResult;

        // Event wird ausgelöst
        $this->dispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PreOrderPlacedEvent::class), PreOrderPlacedEvent::EVENT_NAME);

        // Logger wird aufgerufen
        $this->logger->expects(static::once())->method('info');

        $this->subscriber->onOrderPlaced($event);

        static::assertSame(1, $this->tagRepository->searchIdsCalls);
        static::assertSame(0, $this->tagRepository->createCalls);
        static::assertSame(1, $this->orderRepository->updateCalls);
        static::assertSame([
            [
                'id' => $orderId,
                'tags' => [
                    ['id' => $tagId],
                ],
            ],
        ], $this->orderRepository->updates[0]);
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
        $this->tagRepository->searchIdsCallback = static fn() => $idSearchResult;

        // Event wird ausgelöst
        $this->dispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PreOrderPlacedEvent::class), PreOrderPlacedEvent::EVENT_NAME);

        $this->logger->expects(static::once())->method('info');

        $this->subscriber->onOrderPlaced($event);

        static::assertSame(1, $this->tagRepository->searchIdsCalls);
        static::assertSame(1, $this->tagRepository->createCalls);
        static::assertSame('Vorbestellung', $this->tagRepository->creates[0][0]['name'] ?? null);
        static::assertNotEmpty($this->tagRepository->creates[0][0]['id'] ?? null);
        static::assertSame(1, $this->orderRepository->updateCalls);
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
        $this->tagRepository->searchIdsCallback = static fn() => $idSearchResult;

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

        static::assertSame(1, $this->orderRepository->updateCalls);
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
        $this->tagRepository->searchIdsCallback = static fn() => $idSearchResult;

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

        static::assertSame(1, $this->orderRepository->updateCalls);
    }

    public function testOnOrderPlacedHandlesTagCreationCollisionGracefully(): void
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

        $order->setLineItems(new OrderLineItemCollection([$preOrderItem]));

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        // 1. searchIds returns null on first query, then returns $tagId on collision retry
        $emptyResult = $this->createMock(IdSearchResult::class);
        $emptyResult->method('firstId')->willReturn(null);

        $foundResult = $this->createMock(IdSearchResult::class);
        $foundResult->method('firstId')->willReturn($tagId);

        $searchCount = 0;
        $this->tagRepository->searchIdsCallback = function () use (&$searchCount, $emptyResult, $foundResult) {
            $searchCount++;
            return $searchCount === 1 ? $emptyResult : $foundResult;
        };

        // 2. create throws (simulating race condition / unique constraint collision)
        $this->tagRepository->createCallback = static function () {
            throw new \Exception('Duplicate entry');
        };

        $this->dispatcher->expects(static::once())->method('dispatch');
        $this->logger->expects(static::once())->method('info');

        $this->subscriber->onOrderPlaced($event);

        // 3. Order is still tagged with the retrieved tagId
        static::assertSame(2, $this->tagRepository->searchIdsCalls);
        static::assertSame(1, $this->tagRepository->createCalls);
        static::assertSame(1, $this->orderRepository->updateCalls);
        static::assertSame([
            [
                'id' => $orderId,
                'tags' => [
                    ['id' => $tagId],
                ],
            ],
        ], $this->orderRepository->updates[0]);
    }

    public function testOnOrderPlacedHandlesOrderUpdateExceptionGracefully(): void
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

        $order->setLineItems(new OrderLineItemCollection([$preOrderItem]));

        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getOrder')->willReturn($order);
        $event->method('getContext')->willReturn($this->context);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn($tagId);
        $this->tagRepository->searchIdsCallback = static fn() => $idSearchResult;

        // Order update throws exception
        $this->orderRepository->updateCallback = static function () {
            throw new \Exception('Deadlock found');
        };

        // Error must be logged
        $this->logger->expects(static::once())
            ->method('error')
            ->with(
                'PreOrder: Failed to assign tag to order',
                static::callback(function (array $context) use ($orderId) {
                    return $context['orderId'] === $orderId
                        && str_contains($context['error'], 'Deadlock found');
                })
            );

        // Info log and event dispatch still proceed
        $this->logger->expects(static::once())->method('info');
        $this->dispatcher->expects(static::once())->method('dispatch');

        $this->subscriber->onOrderPlaced($event);

        static::assertSame(1, $this->orderRepository->updateCalls);
    }
}

/**
 * Replaces EntityRepository mock with a dedicated test stub (BT-003).
 * Avoids fragile repository mocking and proxy generator issues,
 * provides explicit call counting and typed closures for deterministic testing.
 *
 * @internal
 */
class StaticTestEntityRepository extends EntityRepository
{
    /** @var (\Closure(Criteria, Context): IdSearchResult)|null */
    public ?\Closure $searchIdsCallback = null;

    /** @var (\Closure(array, Context): EntityWrittenContainerEvent)|null */
    public ?\Closure $createCallback = null;

    /** @var (\Closure(array, Context): EntityWrittenContainerEvent)|null */
    public ?\Closure $updateCallback = null;

    public int $searchIdsCalls = 0;
    public int $createCalls = 0;
    public int $updateCalls = 0;

    /** @var list<array<mixed>> */
    public array $creates = [];

    /** @var list<array<mixed>> */
    public array $updates = [];

    public function __construct()
    {
        // Parameterless constructor, deliberately does not invoke parent::__construct()
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $this->searchIdsCalls++;

        if ($this->searchIdsCallback !== null) {
            return ($this->searchIdsCallback)($criteria, $context);
        }

        return new IdSearchResult(0, [], $criteria, $context);
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->createCalls++;
        $this->creates[] = $data;

        if ($this->createCallback !== null) {
            return ($this->createCallback)($data, $context);
        }

        /** @var EntityWrittenContainerEvent $event */
        $event = (new \ReflectionClass(EntityWrittenContainerEvent::class))->newInstanceWithoutConstructor();
        return $event;
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->updateCalls++;
        $this->updates[] = $data;

        if ($this->updateCallback !== null) {
            return ($this->updateCallback)($data, $context);
        }

        /** @var EntityWrittenContainerEvent $event */
        $event = (new \ReflectionClass(EntityWrittenContainerEvent::class))->newInstanceWithoutConstructor();
        return $event;
    }
}
