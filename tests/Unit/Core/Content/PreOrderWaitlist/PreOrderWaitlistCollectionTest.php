<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Core\Content\PreOrderWaitlist;

use CustomPreOrderManager\Core\Content\PreOrderWaitlist\PreOrderWaitlistCollection;
use CustomPreOrderManager\Core\Content\PreOrderWaitlist\PreOrderWaitlistEntity;
use PHPUnit\Framework\TestCase;

class PreOrderWaitlistCollectionTest extends TestCase
{
    public function testCollectionOperations(): void
    {
        $collection = new PreOrderWaitlistCollection();
        $entity = new PreOrderWaitlistEntity();
        $entity->setId('waitlist-1');

        $collection->add($entity);

        static::assertCount(1, $collection);
        static::assertSame($entity, $collection->get('waitlist-1'));
    }
}
