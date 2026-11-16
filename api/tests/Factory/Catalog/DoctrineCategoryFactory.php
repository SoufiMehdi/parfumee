<?php

declare(strict_types=1);

namespace App\Tests\Factory\Catalog;

use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<DoctrineCategory>
 */
final class DoctrineCategoryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return DoctrineCategory::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => Uuid::v4()->toRfc4122(),
            'name' => self::faker()->words(2, true),
            // Unique column: a random suffix keeps two categories of the same test apart.
            'slug' => self::faker()->unique()->slug(2),
        ];
    }
}
