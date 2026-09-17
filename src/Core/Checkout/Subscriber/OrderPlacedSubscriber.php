<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Checkout\Subscriber;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use CustomPreOrderManager\Core\Checkout\Event\PreOrderPlacedEvent;

class OrderPlacedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $tagRepository,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [CheckoutOrderPlacedEvent::class => 'onOrderPlaced'];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();
        $lineItems = $order->getLineItems();
        if (!$lineItems) {
            return;
        }

        $preOrderItemCount = 0;
        $context = $event->getContext();

        foreach ($lineItems as $item) {
            if (!($item->getPayload()['isPreOrder'] ?? false)) {
                continue;
            }

            $preOrderItemCount++;
            $productId = $item->getReferencedId();

            if ($productId) {
                // Atomares Inkrement der verkauften Menge auf product_translation unter strikter Beachtung der LIVE_VERSION
                $this->connection->executeStatement(
                    'UPDATE `product_translation`
                     SET `custom_fields` = JSON_SET(
                         COALESCE(`custom_fields`, "{}"),
                         "$.custom_preorder_sold_count",
                         COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, "$.custom_preorder_sold_count")), 0) + :qty
                     )
                     WHERE `product_id` = :id AND `product_version_id` = :versionId',
                    [
                        'id' => Uuid::fromHexToBytes($productId),
                        'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                        'qty' => $item->getQuantity(),
                    ]
                );
            }
        }

        if ($preOrderItemCount === 0) {
            return;
        }

        // Tag "Vorbestellung" idempotent finden oder anlegen
        $tagCriteria = (new Criteria())->addFilter(new EqualsFilter('name', 'Vorbestellung'));
        $tagId = $this->tagRepository->searchIds($tagCriteria, $context)->firstId();

        if (!$tagId) {
            $tagId = Uuid::randomHex();
            $this->tagRepository->create([['id' => $tagId, 'name' => 'Vorbestellung']], $context);
        }

        $this->orderRepository->update([
            ['id' => $order->getId(), 'tags' => [['id' => $tagId]]],
        ], $context);

        $this->logger->info('PreOrder: Tag assigned to order', [
            'orderId' => $order->getId(),
            'preOrderItemCount' => $preOrderItemCount,
        ]);

        // Flow Builder Business Event auslösen
        $this->dispatcher->dispatch(
            new PreOrderPlacedEvent($order, $context, $preOrderItemCount),
            PreOrderPlacedEvent::EVENT_NAME
        );
    }
}
