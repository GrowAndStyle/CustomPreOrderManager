<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Integration\Subscriber;

use CustomPreOrderManager\Core\Checkout\Subscriber\OrderPlacedSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class OrderPlacedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private OrderPlacedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new OrderPlacedSubscriber(
            $this->getContainer()->get('order.repository'),
            $this->getContainer()->get('tag.repository'),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('logger')
        );
    }

    public function testSubscribedEvents(): void
    {
        $events = OrderPlacedSubscriber::getSubscribedEvents();
        static::assertArrayHasKey(CheckoutOrderPlacedEvent::class, $events);
        static::assertSame('onOrderPlaced', $events[CheckoutOrderPlacedEvent::class]);
    }

    public function testSubscriberInstantiationWithContainerServices(): void
    {
        static::assertInstanceOf(OrderPlacedSubscriber::class, $this->subscriber);
    }

    public function testOnOrderPlacedWithEmptyLineItems(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setLineItems(new OrderLineItemCollection());

        $event = new CheckoutOrderPlacedEvent(
            Context::createDefaultContext(),
            $order,
            Uuid::randomHex()
        );

        $this->subscriber->onOrderPlaced($event);
        static::assertTrue(true);
    }
}
