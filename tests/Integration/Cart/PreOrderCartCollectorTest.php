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

    public function testCollectEnrichesPreOrderProduct(): void
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
}
