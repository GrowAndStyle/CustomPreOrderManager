<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Core\Content\PreOrderWaitlist;

use CustomPreOrderManager\Core\Content\PreOrderWaitlist\PreOrderWaitlistCollection;
use CustomPreOrderManager\Core\Content\PreOrderWaitlist\PreOrderWaitlistDefinition;
use CustomPreOrderManager\Core\Content\PreOrderWaitlist\PreOrderWaitlistEntity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class PreOrderWaitlistDefinitionTest extends TestCase
{
    private PreOrderWaitlistDefinition $definition;
    private FieldCollection $fields;

    protected function setUp(): void
    {
        $this->definition = new PreOrderWaitlistDefinition();

        // defineFields() per Reflection abrufen, um AssociationField::compile($this->registry) zu umgehen
        $reflection = new \ReflectionMethod($this->definition, 'defineFields');
        $reflection->setAccessible(true);
        $this->fields = $reflection->invoke($this->definition);
    }

    private function findField(string $propertyName): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->getPropertyName() === $propertyName) {
                return $field;
            }
        }

        return null;
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

    public function testFieldCount(): void
    {
        // 14 Felder in defineFields()
        static::assertCount(14, $this->fields);
    }

    public function testIdField(): void
    {
        $field = $this->findField('id');
        static::assertInstanceOf(IdField::class, $field);
        static::assertTrue($field->is(PrimaryKey::class));
        static::assertTrue($field->is(Required::class));
    }

    public function testProductRelationFields(): void
    {
        $productId = $this->findField('productId');
        static::assertInstanceOf(FkField::class, $productId);
        static::assertTrue($productId->is(Required::class));

        $productVersionId = $this->findField('productVersionId');
        static::assertInstanceOf(ReferenceVersionField::class, $productVersionId);
        static::assertSame('product_version_id', $productVersionId->getStorageName());
        static::assertTrue($productVersionId->is(Required::class));

        $product = $this->findField('product');
        static::assertInstanceOf(ManyToOneAssociationField::class, $product);
    }

    public function testSalesChannelRelationFields(): void
    {
        $salesChannelId = $this->findField('salesChannelId');
        static::assertInstanceOf(FkField::class, $salesChannelId);
        static::assertTrue($salesChannelId->is(Required::class));

        $salesChannel = $this->findField('salesChannel');
        static::assertInstanceOf(ManyToOneAssociationField::class, $salesChannel);
    }

    public function testDataFields(): void
    {
        $email = $this->findField('email');
        static::assertInstanceOf(StringField::class, $email);
        static::assertTrue($email->is(Required::class));

        $name = $this->findField('name');
        static::assertInstanceOf(StringField::class, $name);
        static::assertFalse($name->is(Required::class));

        $status = $this->findField('status');
        static::assertInstanceOf(StringField::class, $status);
        static::assertTrue($status->is(Required::class));

        $token = $this->findField('token');
        static::assertInstanceOf(StringField::class, $token);
        static::assertFalse($token->is(Required::class));

        $confirmedAt = $this->findField('confirmedAt');
        static::assertInstanceOf(DateTimeField::class, $confirmedAt);

        $notifiedAt = $this->findField('notifiedAt');
        static::assertInstanceOf(DateTimeField::class, $notifiedAt);

        $createdAt = $this->findField('createdAt');
        static::assertInstanceOf(CreatedAtField::class, $createdAt);

        $updatedAt = $this->findField('updatedAt');
        static::assertInstanceOf(UpdatedAtField::class, $updatedAt);
    }
}
