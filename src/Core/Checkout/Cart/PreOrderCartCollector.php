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

            $hasActiveField = array_key_exists('custom_preorder_active', $customFields);
            $isPreOrder = $hasActiveField
                ? (bool) $customFields['custom_preorder_active']
                : !empty($customFields['custom_preorder_release_date']);

            if (!$isPreOrder) {
                continue;
            }

            $rawDate = $customFields['custom_preorder_release_date'] ?? null;
            $rawText = $customFields['custom_preorder_release_text'] ?? null;
            $inboundStock = (int) ($customFields['custom_preorder_inbound_stock'] ?? 0);
            $soldCount = (int) ($customFields['custom_preorder_sold_count'] ?? 0);
            $remainingQuota = $inboundStock > 0 ? max(0, $inboundStock - $soldCount) : null;

            $formattedDate = null;
            $availableFrom = null;

            if (!empty($rawDate)) {
                try {
                    $dateObj = new \DateTimeImmutable((string) $rawDate);
                    $formattedDate = $dateObj->format('d.m.Y');
                    $availableFrom = 'Voraussichtlich lieferbar ab ' . $formattedDate;
                } catch (\Throwable) {
                    $formattedDate = null;
                    $availableFrom = null;
                }
            }

            $sanitizedNotice = !empty($rawText) ? strip_tags((string) $rawText) : null;

            // Fallback für preOrderReleaseText (Rückwärtskompatibilität)
            if ($availableFrom && $sanitizedNotice) {
                $legacyText = $availableFrom . ' — ' . $sanitizedNotice;
            } elseif ($availableFrom) {
                $legacyText = $availableFrom;
            } elseif ($sanitizedNotice) {
                $legacyText = $sanitizedNotice;
            } else {
                $legacyText = 'Vorbestellung';
            }

            $lineItem->setPayloadValue('isPreOrder', true);
            $lineItem->setPayloadValue('preOrderReleaseDate', $formattedDate);
            $lineItem->setPayloadValue('preOrderNotice', $sanitizedNotice);
            $lineItem->setPayloadValue('preOrderAvailableFrom', $availableFrom);
            $lineItem->setPayloadValue('preOrderReleaseText', $legacyText);
            $lineItem->setPayloadValue('preOrderInboundStock', $inboundStock);
            $lineItem->setPayloadValue('preOrderSoldCount', $soldCount);
            $lineItem->setPayloadValue('preOrderRemainingQuota', $remainingQuota);
        }
    }
}
