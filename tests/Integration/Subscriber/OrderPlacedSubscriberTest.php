<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Integration\Subscriber;

use CustomPreOrderManager\Core\Checkout\Subscriber\OrderPlacedSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class OrderPlacedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSubscribedEvents(): void
    {
        $events = OrderPlacedSubscriber::getSubscribedEvents();
        static::assertArrayHasKey(CheckoutOrderPlacedEvent::class, $events);
        static::assertSame('onOrderPlaced', $events[CheckoutOrderPlacedEvent::class]);
    }

    public function testSubscriberRegisteredInContainer(): void
    {
        $subscriber = $this->getContainer()->get(OrderPlacedSubscriber::class);
        static::assertInstanceOf(OrderPlacedSubscriber::class, $subscriber);
    }
}
