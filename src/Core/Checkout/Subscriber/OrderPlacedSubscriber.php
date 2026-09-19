<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Checkout\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
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
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => ['onOrderPlaced', 100],
        ];
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
        }

        if ($preOrderItemCount === 0) {
            return;
        }

        // Tag "Vorbestellung" idempotent finden oder anlegen (TOCTOU-resilient)
        $tagCriteria = (new Criteria())->addFilter(new EqualsFilter('name', 'Vorbestellung'));
        $tagId = $this->tagRepository->searchIds($tagCriteria, $context)->firstId();

        if (!$tagId) {
            $tagId = Uuid::randomHex();
            try {
                $this->tagRepository->create([['id' => $tagId, 'name' => 'Vorbestellung']], $context);
            } catch (\Throwable) {
                // Bei parallelen Erst-Checkouts greift der andere Prozess: Tag erneut abfragen
                $tagId = $this->tagRepository->searchIds($tagCriteria, $context)->firstId();
            }
        }

        if ($tagId) {
            try {
                $this->orderRepository->update([
                    ['id' => $order->getId(), 'tags' => [['id' => $tagId]]],
                ], $context);
            } catch (\Throwable $e) {
                $this->logger->error('PreOrder: Failed to assign tag to order', [
                    'orderId' => $order->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

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
