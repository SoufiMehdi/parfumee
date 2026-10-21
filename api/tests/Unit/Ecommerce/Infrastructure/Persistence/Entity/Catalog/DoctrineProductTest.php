<?php

namespace App\Tests\Unit\Ecommerce\Infrastructure\Persistence\Entity\Catalog;

use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrinePicture;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use PHPUnit\Framework\TestCase;

class DoctrineProductTest extends TestCase
{
    private DoctrineProduct $product;
    private DoctrineCategory $category;

    protected function setUp(): void
    {
        $this->category = new DoctrineCategory('cat-uuid', 'Electronics', 'electronics');
        $this->product = new DoctrineProduct(
            'prod-uuid',
            'Smartphone',
            999.99,
            $this->category,
            ['color' => 'black']
        );
    }

    public function testGettersReturnCorrectValues(): void
    {
        $this->assertSame('prod-uuid', $this->product->getId());
        $this->assertSame('Smartphone', $this->product->getName());
        $this->assertSame(999.99, $this->product->getPrice());
        $this->assertSame($this->category, $this->product->getCategory());
        $this->assertSame(['color' => 'black'], $this->product->getAttributes());
        $this->assertCount(0, $this->product->getPictures());
    }

    public function testSettersUpdateValues(): void
    {
        $newCategory = new DoctrineCategory('cat-2', 'Gadgets', 'gadgets');
        
        $this->product->setName('New Smartphone');
        $this->product->setPrice(899.99);
        $this->product->setCategory($newCategory);
        $this->product->setAttributes(['color' => 'white', 'storage' => '128GB']);

        $this->assertSame('New Smartphone', $this->product->getName());
        $this->assertSame(899.99, $this->product->getPrice());
        $this->assertSame($newCategory, $this->product->getCategory());
        $this->assertSame(['color' => 'white', 'storage' => '128GB'], $this->product->getAttributes());
    }

    public function testAddPictureAddsToCollectionAndSetsProduct(): void
    {
        $picture = $this->createMock(DoctrinePicture::class);
        
        // On s'assure que setProduct est bien appelé sur l'image
        $picture->expects($this->once())
            ->method('setProduct')
            ->with($this->product);

        $this->product->addPicture($picture);

        $this->assertCount(1, $this->product->getPictures());
        $this->assertTrue($this->product->getPictures()->contains($picture));
    }
}
