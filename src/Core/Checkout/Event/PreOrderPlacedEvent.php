<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Checkout\Event;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Symfony\Contracts\EventDispatcher\Event;

class PreOrderPlacedEvent extends Event implements FlowEventAware, ScalarValuesAware
{
    public const EVENT_NAME = 'preorder.order.placed';

    public function __construct(
        private readonly OrderEntity $order,
        private readonly Context $context,
        private readonly int $preOrderItemCount
    ) {}

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getPreOrderItemCount(): int
    {
        return $this->preOrderItemCount;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('order', new EntityType(OrderDefinition::class))
            ->add('preOrderItemCount', new ScalarValueType(ScalarValueType::TYPE_INT));
    }

    public function getValues(): array
    {
        return ['preOrderItemCount' => $this->preOrderItemCount];
    }
}
