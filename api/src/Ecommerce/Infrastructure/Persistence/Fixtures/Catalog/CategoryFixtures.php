<?php

namespace App\Ecommerce\Infrastructure\Persistence\Fixtures\Catalog;

use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

class CategoryFixtures extends Fixture
{
    public const CAT_BOUGIE_ID = '018f5370-1234-7000-8000-000000000001';
    public const CAT_FONDANT_ID = '018f5370-1234-7000-8000-000000000002';
    
    public function load(ObjectManager $manager): void
    {
        $categories = [
            [
                'id' => self::CAT_BOUGIE_ID,
                'name' => 'Bougies Parfumées',
                'slug' => 'bougies-parfumees'
            ],
            [
                'id' => self::CAT_FONDANT_ID,
                'name' => 'Fondants',
                'slug' => 'fondants'
            ],
            [
                'id' => Uuid::v4()->toRfc4122(),
                'name' => 'Vêtements', // On prépare déjà le terrain générique !
                'slug' => 'vetements'
            ],
        ];

        foreach ($categories as $data) {
            $category = new DoctrineCategory(
                $data['id'],
                $data['name'],
                $data['slug']
            );
            $manager->persist($category);
        }

        $manager->flush();
    }
}