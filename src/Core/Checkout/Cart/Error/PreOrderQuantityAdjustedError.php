<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Checkout\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class PreOrderQuantityAdjustedError extends Error
{
    private const KEY = 'custom-preorder-quantity-adjusted';

    public function __construct(
        private readonly string $lineItemId,
        private readonly string $productName,
        private readonly int $requestedQuantity,
        private readonly int $maxQuantity
    ) {
        $this->message = sprintf(
            'Die gewünschte Bestellmenge für "%s" wurde von %d auf %d Stück angepasst, da das verfügbare Vorbestellkontingent begrenzt ist.',
            $this->productName,
            $this->requestedQuantity,
            $this->maxQuantity
        );
        parent::__construct($this->message);
    }

    public function getId(): string
    {
        return sprintf('%s-%s', self::KEY, $this->lineItemId);
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getLevel(): int
    {
        return self::LEVEL_WARNING;
    }

    public function blockOrder(): bool
    {
        return false;
    }

    public function getParameters(): array
    {
        return [
            '%productName%' => $this->productName,
            '%requestedQuantity%' => $this->requestedQuantity,
            '%maxQuantity%' => $this->maxQuantity,
        ];
    }
}
