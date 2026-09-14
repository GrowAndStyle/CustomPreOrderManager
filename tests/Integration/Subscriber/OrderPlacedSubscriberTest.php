<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Integration\Subscriber;

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

    public function testOnOrderPlacedProcessesPreOrder(): void
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

        // Atomares Inkrement der verkauften Menge
        $connection->expects(static::once())->method('executeStatement');

        // Tag suchen
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn(Uuid::randomHex());
        $tagRepo->method('searchIds')->willReturn($idSearchResult);

        // Order aktualisieren mit Vorbestellung Tag
        $orderRepo->expects(static::once())->method('update');

        // Business Event für Flow Builder auslösen
        $dispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PreOrderPlacedEvent::class), PreOrderPlacedEvent::EVENT_NAME);

        $subscriber->onOrderPlaced($event);
    }
}
