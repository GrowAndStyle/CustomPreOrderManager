<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Checkout\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPlacedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $tagRepository,
        private readonly Connection $connection
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();
        $lineItems = $order->getLineItems();
        if (!$lineItems || $lineItems->count() === 0) {
            return;
        }

        $hasPreOrder = false;
        $context = $event->getContext();

        foreach ($lineItems as $item) {
            $payload = $item->getPayload();
            if ($payload['isPreOrder'] ?? false) {
                $hasPreOrder = true;
                $productId = $item->getReferencedId();

                if ($productId && Uuid::isValid($productId)) {
                    // Atomares Inkrement via DBAL (Race-Condition-sicher)
                    $this->connection->executeStatement(
                        'UPDATE `product` 
                         SET `custom_fields` = JSON_SET(
                             COALESCE(`custom_fields`, "{}"),
                             "$.custom_preorder_sold_count",
                             COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, "$.custom_preorder_sold_count")), 0) + :qty
                         ) 
                         WHERE `id` = :id',
                        [
                            'id' => Uuid::fromHexToBytes($productId),
                            'qty' => $item->getQuantity(),
                        ]
                    );
                }
            }
        }

        if (!$hasPreOrder) {
            return;
        }

        // Tag 'Vorbestellung' finden oder erstellen
        $tagCriteria = (new Criteria())->addFilter(new EqualsFilter('name', 'Vorbestellung'));
        $tagId = $this->tagRepository->searchIds($tagCriteria, $context)->firstId();

        if (!$tagId) {
            $tagId = Uuid::randomHex();
            $this->tagRepository->create([['id' => $tagId, 'name' => 'Vorbestellung']], $context);
        }

        // Tag an Order anheften
        $this->orderRepository->update([
            [
                'id' => $order->getId(),
                'tags' => [
                    ['id' => $tagId],
                ],
            ],
        ], $context);
    }
}
