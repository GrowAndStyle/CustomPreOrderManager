<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Integration\Cart;

use CustomPreOrderManager\Core\Checkout\Cart\PreOrderCartCollector;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PreOrderCartCollectorTest extends TestCase
{
    private PreOrderCartCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new PreOrderCartCollector();
    }

    public function testCollectIgnoresNonProductLineItems(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', ['custom_preorder_active' => true]);
        $cart->add($lineItem);

        $data = new CartDataCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $behavior = new CartBehavior();

        $this->collector->collect($data, $cart, $context, $behavior);

        static::assertNull($lineItem->getPayloadValue('isPreOrder'));
    }

    public function testCollectIgnoresNonPreOrderProduct(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', ['custom_preorder_active' => false]);
        $cart->add($lineItem);

        $data = new CartDataCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $behavior = new CartBehavior();

        $this->collector->collect($data, $cart, $context, $behavior);

        static::assertNull($lineItem->getPayloadValue('isPreOrder'));
    }

    public function testCollectEnrichesPreOrderProductWithCustomText(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', [
            'custom_preorder_active' => true,
            'custom_preorder_release_text' => 'Lieferbar ab November 2026',
        ]);
        $cart->add($lineItem);

        $data = new CartDataCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $behavior = new CartBehavior();

        $this->collector->collect($data, $cart, $context, $behavior);

        static::assertTrue($lineItem->getPayloadValue('isPreOrder'));
        static::assertSame('Lieferbar ab November 2026', $lineItem->getPayloadValue('preOrderReleaseText'));
    }

    public function testCollectSanitizesHtmlInReleaseText(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', [
            'custom_preorder_active' => true,
            'custom_preorder_release_text' => '<script>alert(1)</script><b>Vorbestellbar</b>',
        ]);
        $cart->add($lineItem);

        $data = new CartDataCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $behavior = new CartBehavior();

        $this->collector->collect($data, $cart, $context, $behavior);

        static::assertTrue($lineItem->getPayloadValue('isPreOrder'));
        static::assertSame('alert(1)Vorbestellbar', $lineItem->getPayloadValue('preOrderReleaseText'));
    }

    public function testCollectFallbackToFormattedReleaseDate(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', [
            'custom_preorder_active' => true,
            'custom_preorder_release_text' => '',
            'custom_preorder_release_date' => '2026-10-15T00:00:00+00:00',
        ]);
        $cart->add($lineItem);

        $data = new CartDataCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $behavior = new CartBehavior();

        $this->collector->collect($data, $cart, $context, $behavior);

        static::assertTrue($lineItem->getPayloadValue('isPreOrder'));
        static::assertSame('Lieferbar ab 10/2026', $lineItem->getPayloadValue('preOrderReleaseText'));
    }

    public function testCollectHandlesInvalidReleaseDateGracefully(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', [
            'custom_preorder_active' => true,
            'custom_preorder_release_text' => null,
            'custom_preorder_release_date' => 'definitely-invalid-date-string',
        ]);
        $cart->add($lineItem);

        $data = new CartDataCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $behavior = new CartBehavior();

        $this->collector->collect($data, $cart, $context, $behavior);

        static::assertTrue($lineItem->getPayloadValue('isPreOrder'));
        static::assertSame('Vorbestellung', $lineItem->getPayloadValue('preOrderReleaseText'));
    }

    public function testCollectFallbackWithoutTextAndDate(): void
    {
        $cart = new Cart('test-token');
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('customFields', [
            'custom_preorder_active' => true,
        ]);
        $cart->add($lineItem);

        $data = new CartDataCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $behavior = new CartBehavior();

        $this->collector->collect($data, $cart, $context, $behavior);

        static::assertTrue($lineItem->getPayloadValue('isPreOrder'));
        static::assertSame('Vorbestellung', $lineItem->getPayloadValue('preOrderReleaseText'));
    }
}
