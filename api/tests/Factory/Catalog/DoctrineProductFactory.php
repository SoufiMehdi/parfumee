<?php

declare(strict_types=1);

namespace App\Tests\Factory\Catalog;

use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<DoctrineProduct>
 */
final class DoctrineProductFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return DoctrineProduct::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => Uuid::v4()->toRfc4122(),
            'name' => self::faker()->words(3, true),
            'slug' => self::faker()->unique()->slug(3),
            'price' => self::faker()->randomFloat(2, 5, 60),
            'category' => DoctrineCategoryFactory::new(),
            'attributes' => [
                'weight' => 180.0,
                'fragrance' => 'amber',
                'size' => 'M',
                'description' => null,
                'presentation' => null,
            ],
        ];
    }
}
