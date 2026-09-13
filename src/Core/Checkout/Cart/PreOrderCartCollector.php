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

            // Nur wenn Vorbestellung aktiv
            if (!($customFields['custom_preorder_active'] ?? false)) {
                continue;
            }

            // Physischer Lagerbestand sticht Vorbestellung: Wenn Ware lagernd ist (> 0), keine Vorbestellung
            $stock = null;
            if ($lineItem->getDeliveryInformation() !== null) {
                $stock = $lineItem->getDeliveryInformation()->getStock();
            } elseif (isset($payload['stock'])) {
                $stock = (int) $payload['stock'];
            }

            if ($stock !== null && $stock > 0) {
                continue;
            }

            $releaseText = $customFields['custom_preorder_release_text'] ?? null;
            if (empty($releaseText) && !empty($customFields['custom_preorder_release_date'])) {
                try {
                    $date = new \DateTimeImmutable($customFields['custom_preorder_release_date']);
                    $releaseText = 'Lieferbar ab ' . $date->format('m/Y');
                } catch (\Throwable) {
                    $releaseText = 'Vorbestellung';
                }
            }

            if (empty($releaseText)) {
                $releaseText = 'Vorbestellung';
            }

            // Unveränderliche Vorbestell-Daten an Position verankern
            $lineItem->setPayloadValue('isPreOrder', true);
            $lineItem->setPayloadValue('preOrderReleaseText', $releaseText);
        }
    }
}
