<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PreOrderCartCollector implements CartDataCollectorInterface
{
    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        foreach ($original->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $payload = $lineItem->getPayload();
            $customFields = $payload['customFields'] ?? [];

            $isPreOrder = ($customFields['custom_preorder_active'] ?? false)
                || !empty($customFields['custom_preorder_release_date']);

            if (!$isPreOrder) {
                continue;
            }

            $releaseText = $customFields['custom_preorder_release_text'] ?? null;
            if (empty($releaseText) && !empty($customFields['custom_preorder_release_date'])) {
                try {
                    $date = new \DateTimeImmutable((string) $customFields['custom_preorder_release_date']);
                    $releaseText = 'Voraussichtlich lieferbar ab ' . $date->format('d.m.Y');
                } catch (\Throwable) {
                    $releaseText = 'Vorbestellung';
                }
            }

            // XSS-Schutz: Freitext vor dem Ablegen im Payload bereinigen
            $sanitizedText = !empty($releaseText) ? strip_tags((string) $releaseText) : 'Vorbestellung';

            $lineItem->setPayloadValue('isPreOrder', true);
            $lineItem->setPayloadValue('preOrderReleaseText', $sanitizedText);
        }
    }
}
