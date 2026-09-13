<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Core\Checkout\Cart;

use CustomPreOrderManager\Core\Checkout\Cart\PreOrderCartCollector;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PreOrderCartCollectorTest extends TestCase
{
    private PreOrderCartCollector $collector;
    private CartDataCollection $data;
    private SalesChannelContext $context;
    private CartBehavior $behavior;

    protected function setUp(): void
    {
        $this->collector = new PreOrderCartCollector();
        $this->data = new CartDataCollection();
        $this->context = $this->createMock(SalesChannelContext::class);
        $this->behavior = $this->createMock(CartBehavior::class);
    }

    public function testCollectIgnoresNonProductLineItems(): void
    {
        $cart = new Cart('test-token');
        $customLineItem = new LineItem('custom-1', LineItem::CUSTOM_LINE_ITEM_TYPE);
        $customLineItem->setPayloadValue('customFields', ['custom_preorder_active' => true]);
        $cart->addLineItems(new LineItemCollection([$customLineItem]));

        $this->collector->collect($this->data, $cart, $this->context, $this->behavior);

        static::assertNull($customLineItem->getPayloadValue('isPreOrder'));
    }

    public function testCollectIgnoresWhenPreOrderNotActive(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem('prod-1', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', ['custom_preorder_active' => false]);
        $cart->addLineItems(new LineItemCollection([$lineItem]));

        $this->collector->collect($this->data, $cart, $this->context, $this->behavior);

        static::assertNull($lineItem->getPayloadValue('isPreOrder'));
    }

    public function testCollectIgnoresWhenDeliveryInformationStockGreaterThanZero(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem('prod-1', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', ['custom_preorder_active' => true]);

        $deliveryInfo = $this->createMock(DeliveryInformation::class);
        $deliveryInfo->method('getStock')->willReturn(5);
        $lineItem->setDeliveryInformation($deliveryInfo);

        $cart->addLineItems(new LineItemCollection([$lineItem]));

        $this->collector->collect($this->data, $cart, $this->context, $this->behavior);

        static::assertNull($lineItem->getPayloadValue('isPreOrder'));
    }

    public function testCollectIgnoresWhenPayloadStockGreaterThanZero(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem('prod-1', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', ['custom_preorder_active' => true]);
        $lineItem->setPayloadValue('stock', 3);

        $cart->addLineItems(new LineItemCollection([$lineItem]));

        $this->collector->collect($this->data, $cart, $this->context, $this->behavior);

        static::assertNull($lineItem->getPayloadValue('isPreOrder'));
    }

    public function testCollectEnrichesWithExplicitReleaseText(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem('prod-1', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', [
            'custom_preorder_active' => true,
            'custom_preorder_release_text' => 'Lieferbar ab Ende Oktober 2026',
        ]);
        $lineItem->setPayloadValue('stock', 0);

        $cart->addLineItems(new LineItemCollection([$lineItem]));

        $this->collector->collect($this->data, $cart, $this->context, $this->behavior);

        static::assertTrue($lineItem->getPayloadValue('isPreOrder'));
        static::assertSame('Lieferbar ab Ende Oktober 2026', $lineItem->getPayloadValue('preOrderReleaseText'));
    }

    public function testCollectEnrichesWithFormattedReleaseDateWhenNoText(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem('prod-1', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', [
            'custom_preorder_active' => true,
            'custom_preorder_release_date' => '2026-11-15T00:00:00.000Z',
        ]);

        $deliveryInfo = $this->createMock(DeliveryInformation::class);
        $deliveryInfo->method('getStock')->willReturn(0);
        $lineItem->setDeliveryInformation($deliveryInfo);

        $cart->addLineItems(new LineItemCollection([$lineItem]));

        $this->collector->collect($this->data, $cart, $this->context, $this->behavior);

        static::assertTrue($lineItem->getPayloadValue('isPreOrder'));
        static::assertSame('Lieferbar ab 11/2026', $lineItem->getPayloadValue('preOrderReleaseText'));
    }

    public function testCollectFallbackOnInvalidReleaseDate(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem('prod-1', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', [
            'custom_preorder_active' => true,
            'custom_preorder_release_date' => 'not-a-valid-date-string-!!!',
        ]);
        $lineItem->setPayloadValue('stock', 0);

        $cart->addLineItems(new LineItemCollection([$lineItem]));

        $this->collector->collect($this->data, $cart, $this->context, $this->behavior);

        static::assertTrue($lineItem->getPayloadValue('isPreOrder'));
        static::assertSame('Vorbestellung', $lineItem->getPayloadValue('preOrderReleaseText'));
    }

    public function testCollectFallbackWhenNoDateAndNoTextProvided(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem('prod-1', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', [
            'custom_preorder_active' => true,
        ]);
        $lineItem->setPayloadValue('stock', 0);

        $cart->addLineItems(new LineItemCollection([$lineItem]));

        $this->collector->collect($this->data, $cart, $this->context, $this->behavior);

        static::assertTrue($lineItem->getPayloadValue('isPreOrder'));
        static::assertSame('Vorbestellung', $lineItem->getPayloadValue('preOrderReleaseText'));
    }

    public function testCollectEnrichesWhenNoDeliveryInfoAndNoStockInPayload(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem('prod-1', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', [
            'custom_preorder_active' => true,
            'custom_preorder_release_text' => 'Lieferbar bald',
        ]);

        $cart->addLineItems(new LineItemCollection([$lineItem]));

        $this->collector->collect($this->data, $cart, $this->context, $this->behavior);

        static::assertTrue($lineItem->getPayloadValue('isPreOrder'));
        static::assertSame('Lieferbar bald', $lineItem->getPayloadValue('preOrderReleaseText'));
    }

    public function testCollectProcessesMultipleMixedLineItems(): void
    {
        $cart = new Cart('test-token');
        $normalItem = new LineItem('prod-normal', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $normalItem->setPayloadValue('customFields', ['custom_preorder_active' => false]);

        $preOrderItem = new LineItem('prod-preorder', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $preOrderItem->setPayloadValue('customFields', [
            'custom_preorder_active' => true,
            'custom_preorder_release_text' => 'Herbst 2026',
        ]);

        $cart->addLineItems(new LineItemCollection([$normalItem, $preOrderItem]));

        $this->collector->collect($this->data, $cart, $this->context, $this->behavior);

        static::assertNull($normalItem->getPayloadValue('isPreOrder'));
        static::assertTrue($preOrderItem->getPayloadValue('isPreOrder'));
        static::assertSame('Herbst 2026', $preOrderItem->getPayloadValue('preOrderReleaseText'));
    }
}
