<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Core\Content\PreOrderWaitlist;

use CustomPreOrderManager\Core\Content\PreOrderWaitlist\PreOrderWaitlistCollection;
use CustomPreOrderManager\Core\Content\PreOrderWaitlist\PreOrderWaitlistDefinition;
use CustomPreOrderManager\Core\Content\PreOrderWaitlist\PreOrderWaitlistEntity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class PreOrderWaitlistDefinitionTest extends TestCase
{
    private PreOrderWaitlistDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new PreOrderWaitlistDefinition();
    }

    public function testEntityName(): void
    {
        static::assertSame('custom_preorder_waitlist', $this->definition->getEntityName());
    }

    public function testEntityClass(): void
    {
        static::assertSame(PreOrderWaitlistEntity::class, $this->definition->getEntityClass());
    }

    public function testCollectionClass(): void
    {
        static::assertSame(PreOrderWaitlistCollection::class, $this->definition->getCollectionClass());
    }

    public function testFields(): void
    {
        $fields = $this->definition->getFields();

        static::assertInstanceOf(IdField::class, $fields->get('id'));
        static::assertTrue($fields->get('id')->is(PrimaryKey::class));
        static::assertTrue($fields->get('id')->is(Required::class));

        static::assertInstanceOf(FkField::class, $fields->get('productId'));
        static::assertSame(ProductDefinition::class, $fields->get('productId')->getReferenceClass());
        static::assertTrue($fields->get('productId')->is(Required::class));

        static::assertInstanceOf(ReferenceVersionField::class, $fields->get('productVersionId'));
        static::assertSame('product_version_id', $fields->get('productVersionId')->getStorageName());
        static::assertTrue($fields->get('productVersionId')->is(Required::class));

        static::assertInstanceOf(ManyToOneAssociationField::class, $fields->get('product'));

        static::assertInstanceOf(FkField::class, $fields->get('salesChannelId'));
        static::assertSame(SalesChannelDefinition::class, $fields->get('salesChannelId')->getReferenceClass());
        static::assertTrue($fields->get('salesChannelId')->is(Required::class));

        static::assertInstanceOf(ManyToOneAssociationField::class, $fields->get('salesChannel'));

        static::assertInstanceOf(StringField::class, $fields->get('email'));
        static::assertTrue($fields->get('email')->is(Required::class));

        static::assertInstanceOf(StringField::class, $fields->get('name'));
        static::assertFalse($fields->get('name')->is(Required::class));

        static::assertInstanceOf(StringField::class, $fields->get('status'));
        static::assertTrue($fields->get('status')->is(Required::class));

        static::assertInstanceOf(StringField::class, $fields->get('token'));
        static::assertInstanceOf(DateTimeField::class, $fields->get('confirmedAt'));
        static::assertInstanceOf(DateTimeField::class, $fields->get('notifiedAt'));
        static::assertInstanceOf(CreatedAtField::class, $fields->get('createdAt'));
        static::assertInstanceOf(UpdatedAtField::class, $fields->get('updatedAt'));
    }
}
