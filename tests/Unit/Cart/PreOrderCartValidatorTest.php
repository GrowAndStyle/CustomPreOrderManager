<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Cart;

use CustomPreOrderManager\Core\Checkout\Cart\Error\PreOrderQuantityAdjustedError;
use CustomPreOrderManager\Core\Checkout\Cart\Error\PreOrderQuotaExhaustedError;
use CustomPreOrderManager\Core\Checkout\Cart\PreOrderCartValidator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PreOrderCartValidatorTest extends TestCase
{
    private PreOrderCartValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PreOrderCartValidator();
    }

    public function testValidateIgnoresNonProductLineItems(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE);
        $lineItem->setStackable(true);
        $lineItem->setPayloadValue('isPreOrder', true);
        $lineItem->setPayloadValue('preOrderInboundStock', 5);
        $lineItem->setPayloadValue('preOrderSoldCount', 4);
        $lineItem->setQuantity(3);
        $cart->add($lineItem);

        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);

        $this->validator->validate($cart, $errors, $context);

        static::assertCount(0, $errors);
        static::assertSame(3, $lineItem->getQuantity());
    }

    public function testValidateIgnoresNonPreOrderProducts(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setStackable(true);
        $lineItem->setPayloadValue('isPreOrder', false);
        $lineItem->setQuantity(10);
        $cart->add($lineItem);

        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);

        $this->validator->validate($cart, $errors, $context);

        static::assertCount(0, $errors);
        static::assertSame(10, $lineItem->getQuantity());
    }

    public function testValidateIgnoresPreOrderWithoutInboundStockLimit(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setStackable(true);
        $lineItem->setPayloadValue('isPreOrder', true);
        $lineItem->setPayloadValue('preOrderInboundStock', 0);
        $lineItem->setQuantity(50);
        $cart->add($lineItem);

        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);

        $this->validator->validate($cart, $errors, $context);

        static::assertCount(0, $errors);
        static::assertSame(50, $lineItem->getQuantity());
    }

    public function testValidateAllowsQuantityWithinRemainingQuota(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setStackable(true);
        $lineItem->setPayloadValue('isPreOrder', true);
        $lineItem->setPayloadValue('preOrderInboundStock', 10);
        $lineItem->setPayloadValue('preOrderSoldCount', 6);
        $lineItem->setQuantity(4);
        $cart->add($lineItem);

        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);

        $this->validator->validate($cart, $errors, $context);

        static::assertCount(0, $errors);
        static::assertSame(4, $lineItem->getQuantity());
    }

    public function testValidateAdjustsQuantityAndAddsWarningWhenQuantityExceedsRemainingQuota(): void
    {
        $cart = new Cart('test-token');
        $lineItemId = Uuid::randomHex();
        $lineItem = new LineItem($lineItemId, LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setStackable(true);
        $lineItem->setLabel('Sonderedition Uhr');
        $lineItem->setPayloadValue('isPreOrder', true);
        $lineItem->setPayloadValue('preOrderInboundStock', 10);
        $lineItem->setPayloadValue('preOrderSoldCount', 8); // Restquote = 2
        $lineItem->setQuantity(5); // Kunde verlangt 5 Stück
        $cart->add($lineItem);

        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);

        $this->validator->validate($cart, $errors, $context);

        // Prüfung: Menge wurde auf Restquote 2 korrigiert
        static::assertSame(2, $lineItem->getQuantity());

        // Prüfung: Warnmeldung vorhanden
        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(PreOrderQuantityAdjustedError::class, $error);
        static::assertSame('custom-preorder-quantity-adjusted', $error->getMessageKey());
        static::assertSame(\Shopware\Core\Checkout\Cart\Error\Error::LEVEL_WARNING, $error->getLevel());
        static::assertFalse($error->blockOrder());

        $params = $error->getParameters();
        static::assertSame('Sonderedition Uhr', $params['%productName%']);
        static::assertSame(5, $params['%requestedQuantity%']);
        static::assertSame(2, $params['%maxQuantity%']);
    }

    public function testValidateRemovesLineItemAndAddsErrorWhenQuotaIsExhausted(): void
    {
        $cart = new Cart('test-token');
        $lineItemId = Uuid::randomHex();
        $lineItem = new LineItem($lineItemId, LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setStackable(true);
        $lineItem->setLabel('Ausverkauftes Buch');
        $lineItem->setPayloadValue('isPreOrder', true);
        $lineItem->setPayloadValue('preOrderInboundStock', 10);
        $lineItem->setPayloadValue('preOrderSoldCount', 10); // Restquote = 0
        $lineItem->setQuantity(1);
        $cart->add($lineItem);

        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);

        $this->validator->validate($cart, $errors, $context);

        // Prüfung: Artikel wurde aus Cart entfernt
        static::assertNull($cart->getLineItems()->get($lineItemId));

        // Prüfung: Error vorhanden
        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(PreOrderQuotaExhaustedError::class, $error);
        static::assertSame('custom-preorder-quota-exhausted', $error->getMessageKey());
        static::assertSame(\Shopware\Core\Checkout\Cart\Error\Error::LEVEL_ERROR, $error->getLevel());
        static::assertTrue($error->blockOrder());

        $params = $error->getParameters();
        static::assertSame('Ausverkauftes Buch', $params['%productName%']);
    }
}
