<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Checkout\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class PreOrderQuotaExhaustedError extends Error
{
    private const KEY = 'custom-preorder-quota-exhausted';

    public function __construct(
        private readonly string $lineItemId,
        private readonly string $productName
    ) {
        $this->message = sprintf(
            'Das Produkt "%s" musste aus dem Warenkorb entfernt werden, da das Vorbestellkontingent soeben vollständig vergriffen ist.',
            $this->productName
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
        return self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getParameters(): array
    {
        return [
            '%productName%' => $this->productName,
        ];
    }
}
