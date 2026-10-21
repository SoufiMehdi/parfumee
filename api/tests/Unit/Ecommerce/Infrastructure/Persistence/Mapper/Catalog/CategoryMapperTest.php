<?php

namespace App\Tests\Unit\Ecommerce\Infrastructure\Persistence\Mapper\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\CategoryMapper;
use PHPUnit\Framework\TestCase;

class CategoryMapperTest extends TestCase
{
    private CategoryMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new CategoryMapper();
    }

    public function testToInfrastructureReturnsDoctrineCategory(): void
    {
        $domainCategory = new Category('cat-uuid', 'Electronics', 'electronics');

        $doctrineCategory = $this->mapper->toInfrastructure($domainCategory);

        $this->assertInstanceOf(DoctrineCategory::class, $doctrineCategory);
        $this->assertSame('cat-uuid', $doctrineCategory->getId());
        $this->assertSame('Electronics', $doctrineCategory->getName());
        $this->assertSame('electronics', $doctrineCategory->getSlug());
    }

    public function testToDomainReturnsDomainCategory(): void
    {
        $doctrineCategory = new DoctrineCategory('cat-uuid', 'Electronics', 'electronics');

        $domainCategory = $this->mapper->toDomain($doctrineCategory);

        $this->assertInstanceOf(Category::class, $domainCategory);
        $this->assertSame('cat-uuid', $domainCategory->getId());
        $this->assertSame('Electronics', $domainCategory->getName());
        $this->assertSame('electronics', $domainCategory->getSlug());
    }
}