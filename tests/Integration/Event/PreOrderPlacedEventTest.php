<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Integration\Event;

use CustomPreOrderManager\Core\Checkout\Event\PreOrderPlacedEvent;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;

class PreOrderPlacedEventTest extends TestCase
{
    public function testEventPropertiesAndMethods(): void
    {
        $order = new OrderEntity();
        $context = Context::createDefaultContext();
        $count = 3;

        $event = new PreOrderPlacedEvent($order, $context, $count);

        static::assertSame(PreOrderPlacedEvent::EVENT_NAME, $event->getName());
        static::assertSame($order, $event->getOrder());
        static::assertSame($context, $event->getContext());
        static::assertSame($count, $event->getPreOrderItemCount());

        $values = $event->getValues();
        static::assertArrayHasKey('preOrderItemCount', $values);
        static::assertSame(3, $values['preOrderItemCount']);

        $availableData = PreOrderPlacedEvent::getAvailableData();
        static::assertTrue($availableData->has('order'));
        static::assertTrue($availableData->has('preOrderItemCount'));
        static::assertInstanceOf(EntityType::class, $availableData->get('order'));
        static::assertInstanceOf(ScalarValueType::class, $availableData->get('preOrderItemCount'));
    }
}
