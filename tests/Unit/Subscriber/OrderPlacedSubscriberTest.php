<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Subscriber;

use CustomPreOrderManager\Core\Checkout\Event\PreOrderPlacedEvent;
use CustomPreOrderManager\Core\Checkout\Subscriber\OrderPlacedSubscriber;
use Doctrine\DBAL\Connection;
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
    public function testSubscribedEvents(): void
    {
        $events = OrderPlacedSubscriber::getSubscribedEvents();
        static::assertArrayHasKey(CheckoutOrderPlacedEvent::class, $events);
        static::assertSame('onOrderPlaced', $events[CheckoutOrderPlacedEvent::class]);
    }

    public function testOnOrderPlacedReturnsEarlyWhenLineItemsNull(): void
    {
        $orderRepo = $this->createMock(EntityRepository::class);
        $tagRepo = $this->createMock(EntityRepository::class);
        $connection = $this->createMock(Connection::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $subscriber = new OrderPlacedSubscriber($orderRepo, $tagRepo, $connection, $dispatcher, $logger);

        $order = $this->createMock(OrderEntity::class);
        $order->method('getLineItems')->willReturn(null);

        $context = Context::createDefaultContext();
        $event = new CheckoutOrderPlacedEvent($context, $order, Uuid::randomHex());

        $connection->expects(static::never())->method('executeStatement');
        $orderRepo->expects(static::never())->method('update');
        $dispatcher->expects(static::never())->method('dispatch');

        $subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedReturnsEarlyWhenNoPreOrderItems(): void
    {
        $orderRepo = $this->createMock(EntityRepository::class);
        $tagRepo = $this->createMock(EntityRepository::class);
        $connection = $this->createMock(Connection::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $subscriber = new OrderPlacedSubscriber($orderRepo, $tagRepo, $connection, $dispatcher, $logger);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $regularItem = new OrderLineItemEntity();
        $regularItem->setId(Uuid::randomHex());
        $regularItem->setReferencedId(Uuid::randomHex());
        $regularItem->setQuantity(1);
        $regularItem->setPayload(['isPreOrder' => false]);

        $order->setLineItems(new OrderLineItemCollection([$regularItem]));

        $context = Context::createDefaultContext();
        $event = new CheckoutOrderPlacedEvent($context, $order, Uuid::randomHex());

        $connection->expects(static::never())->method('executeStatement');
        $orderRepo->expects(static::never())->method('update');
        $dispatcher->expects(static::never())->method('dispatch');

        $subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedCreatesTagWhenNotExisting(): void
    {
        $orderRepo = $this->createMock(EntityRepository::class);
        $tagRepo = $this->createMock(EntityRepository::class);
        $connection = $this->createMock(Connection::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $subscriber = new OrderPlacedSubscriber($orderRepo, $tagRepo, $connection, $dispatcher, $logger);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setReferencedId(Uuid::randomHex());
        $lineItem->setQuantity(3);
        $lineItem->setPayload(['isPreOrder' => true]);

        $itemWithoutProductId = new OrderLineItemEntity();
        $itemWithoutProductId->setId(Uuid::randomHex());
        $itemWithoutProductId->setReferencedId(null);
        $itemWithoutProductId->setQuantity(1);
        $itemWithoutProductId->setPayload(['isPreOrder' => true]);

        $order->setLineItems(new OrderLineItemCollection([$lineItem, $itemWithoutProductId]));

        $context = Context::createDefaultContext();
        $event = new CheckoutOrderPlacedEvent($context, $order, Uuid::randomHex());

        // Atomares Inkrement nur für das Item mit referencedId
        $connection->expects(static::once())->method('executeStatement');

        // Tag existiert noch nicht
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn(null);
        $tagRepo->method('searchIds')->willReturn($idSearchResult);

        // Tag erstellen wird aufgerufen
        $tagRepo->expects(static::once())->method('create');

        // Order wird aktualisiert
        $orderRepo->expects(static::once())->method('update');

        // Event wird ausgelöst
        $dispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PreOrderPlacedEvent::class), PreOrderPlacedEvent::EVENT_NAME);

        $logger->expects(static::once())->method('info');

        $subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedUsesExistingTag(): void
    {
        $orderRepo = $this->createMock(EntityRepository::class);
        $tagRepo = $this->createMock(EntityRepository::class);
        $connection = $this->createMock(Connection::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $subscriber = new OrderPlacedSubscriber($orderRepo, $tagRepo, $connection, $dispatcher, $logger);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setReferencedId(Uuid::randomHex());
        $lineItem->setQuantity(2);
        $lineItem->setPayload(['isPreOrder' => true]);

        $order->setLineItems(new OrderLineItemCollection([$lineItem]));

        $context = Context::createDefaultContext();
        $event = new CheckoutOrderPlacedEvent($context, $order, Uuid::randomHex());

        $connection->expects(static::once())->method('executeStatement');

        // Tag existiert bereits
        $existingTagId = Uuid::randomHex();
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn($existingTagId);
        $tagRepo->method('searchIds')->willReturn($idSearchResult);

        // Tag erstellen wird NICHT aufgerufen
        $tagRepo->expects(static::never())->method('create');

        // Order aktualisieren
        $orderRepo->expects(static::once())
            ->method('update')
            ->with(static::callback(function (array $payload) use ($existingTagId) {
                return $payload[0]['tags'][0]['id'] === $existingTagId;
            }));

        $dispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PreOrderPlacedEvent::class), PreOrderPlacedEvent::EVENT_NAME);

        $subscriber->onOrderPlaced($event);
    }
}
