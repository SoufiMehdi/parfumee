<?php

declare(strict_types=1);

namespace App\Tests\Factory\Catalog;

use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrinePicture;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<DoctrinePicture>
 */
final class DoctrinePictureFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return DoctrinePicture::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => Uuid::v4()->toRfc4122(),
            'url' => 'products/'.self::faker()->unique()->slug(2).'.jpg',
            'alt' => self::faker()->sentence(3),
            'sortOrder' => 0,
            'product' => DoctrineProductFactory::new(),
        ];
    }
}
