<?php

namespace App\Tests\Unit\Ecommerce\Infrastructure\Persistence\Entity\Catalog;

use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrinePicture;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use PHPUnit\Framework\TestCase;

class DoctrinePictureTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $picture = new DoctrinePicture('pic-1', 'http://example.com/img.jpg');

        $picture->setAlt('Image alt');
        $picture->setSortOrder(1);

        $this->assertSame('pic-1', $picture->getId());
        $this->assertSame('http://example.com/img.jpg', $picture->getUrl());
        $this->assertSame('Image alt', $picture->getAlt());
        $this->assertSame(1, $picture->getSortOrder());
    }

    public function testAssociations(): void
    {
        $picture = new DoctrinePicture('pic-1', 'url');
        $product = $this->createMock(DoctrineProduct::class);
        $category = $this->createMock(DoctrineCategory::class);

        $picture->setProduct($product);
        $picture->setCategory($category);

        $this->assertSame($product, $picture->getProduct());
        $this->assertSame($category, $picture->getCategory());
    }
}