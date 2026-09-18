<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Content\PreOrderWaitlist;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PreOrderWaitlistEntity>
 */
class PreOrderWaitlistCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PreOrderWaitlistEntity::class;
    }
}
