<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Checkout\Cart;

use CustomPreOrderManager\Core\Checkout\Cart\Error\PreOrderQuantityAdjustedError;
use CustomPreOrderManager\Core\Checkout\Cart\Error\PreOrderQuotaExhaustedError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PreOrderCartValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        foreach ($cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $payload = $lineItem->getPayload();
            if (!($payload['isPreOrder'] ?? false)) {
                continue;
            }

            $inboundStock = (int) ($payload['preOrderInboundStock'] ?? 0);
            // Wenn kein Inbound-Stock definiert ist (0), gilt das Kontingent als unbegrenzt
            if ($inboundStock <= 0) {
                continue;
            }

            $soldCount = (int) ($payload['preOrderSoldCount'] ?? 0);
            $remainingQuota = max(0, $inboundStock - $soldCount);
            $currentQuantity = $lineItem->getQuantity();
            $productName = (string) ($lineItem->getLabel() ?? $payload['productNumber'] ?? 'Vorbestellartikel');

            // Fall 1: Restkontingent vollständig erschöpft
            if ($remainingQuota <= 0) {
                $cart->getLineItems()->remove($lineItem->getId());
                $errors->add(new PreOrderQuotaExhaustedError($lineItem->getId(), $productName));
                continue;
            }

            // Fall 2: Gewünschte Menge überschreitet das verbleibende Kontingent
            if ($currentQuantity > $remainingQuota) {
                $lineItem->setStackable(true);
                $lineItem->setQuantity($remainingQuota);
                $errors->add(new PreOrderQuantityAdjustedError(
                    $lineItem->getId(),
                    $productName,
                    $currentQuantity,
                    $remainingQuota
                ));
            }
        }
    }
}
