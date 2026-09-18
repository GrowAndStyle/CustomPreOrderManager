<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Core\Content\PreOrderWaitlist;

use CustomPreOrderManager\Core\Content\PreOrderWaitlist\PreOrderWaitlistEntity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class PreOrderWaitlistEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $entity = new PreOrderWaitlistEntity();
        $id = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';
        $productId = '11111111111111111111111111111111';
        $productVersionId = '22222222222222222222222222222222';
        $salesChannelId = '33333333333333333333333333333333';
        $email = 'kunde@growandstyle.de';
        $name = 'Max Mustermann';
        $status = 'waiting';
        $token = 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890';
        $confirmedAt = new \DateTimeImmutable('2026-10-01 12:00:00');
        $notifiedAt = new \DateTimeImmutable('2026-10-15 14:30:00');

        $product = new ProductEntity();
        $product->setId($productId);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $entity->setId($id);
        $entity->setProductId($productId);
        $entity->setProductVersionId($productVersionId);
        $entity->setProduct($product);
        $entity->setSalesChannelId($salesChannelId);
        $entity->setSalesChannel($salesChannel);
        $entity->setEmail($email);
        $entity->setName($name);
        $entity->setStatus($status);
        $entity->setToken($token);
        $entity->setConfirmedAt($confirmedAt);
        $entity->setNotifiedAt($notifiedAt);

        static::assertSame($id, $entity->getId());
        static::assertSame($productId, $entity->getProductId());
        static::assertSame($productVersionId, $entity->getProductVersionId());
        static::assertSame($product, $entity->getProduct());
        static::assertSame($salesChannelId, $entity->getSalesChannelId());
        static::assertSame($salesChannel, $entity->getSalesChannel());
        static::assertSame($email, $entity->getEmail());
        static::assertSame($name, $entity->getName());
        static::assertSame($status, $entity->getStatus());
        static::assertSame($token, $entity->getToken());
        static::assertSame($confirmedAt, $entity->getConfirmedAt());
        static::assertSame($notifiedAt, $entity->getNotifiedAt());
    }

    public function testDefaultStatus(): void
    {
        $entity = new PreOrderWaitlistEntity();
        static::assertSame('pending_doi', $entity->getStatus());
        static::assertNull($entity->getName());
        static::assertNull($entity->getToken());
        static::assertNull($entity->getConfirmedAt());
        static::assertNull($entity->getNotifiedAt());
        static::assertNull($entity->getProduct());
        static::assertNull($entity->getSalesChannel());
    }
}
