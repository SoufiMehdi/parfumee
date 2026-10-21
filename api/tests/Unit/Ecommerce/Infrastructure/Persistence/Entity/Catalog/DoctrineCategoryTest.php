<?php

namespace App\Tests\Unit\Ecommerce\Infrastructure\Persistence\Entity\Catalog;

use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use PHPUnit\Framework\TestCase;

class DoctrineCategoryTest extends TestCase
{
    private DoctrineCategory $category;

    protected function setUp(): void
    {
        $this->category = new DoctrineCategory('cat-uuid', 'Electronics', 'electronics');
    }

    public function testGettersReturnCorrectValues(): void
    {
        $this->assertSame('cat-uuid', $this->category->getId());
        $this->assertSame('Electronics', $this->category->getName());
        $this->assertSame('electronics', $this->category->getSlug());
    }

    public function testSetNameUpdatesValue(): void
    {
        $this->category->setName('Home Appliances');
        
        $this->assertSame('Home Appliances', $this->category->getName());
    }

    public function testSetSlugUpdatesValue(): void
    {
        $this->category->setSlug('home-appliances');
        
        $this->assertSame('home-appliances', $this->category->getSlug());
    }
}