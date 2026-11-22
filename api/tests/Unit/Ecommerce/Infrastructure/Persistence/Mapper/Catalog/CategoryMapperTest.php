<?php

declare(strict_types=1);

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
        $this->assertTrue($domainCategory->isTopLevel());
    }

    public function testTheParentGoesBothWays(): void
    {
        $parentEntity = new DoctrineCategory('parent-uuid', 'Bougies', 'bougies');

        $child = $this->mapper->toDomain(new DoctrineCategory('cat-uuid', 'Fondants', 'fondants', $parentEntity));
        $entity = $this->mapper->toInfrastructure($child, $parentEntity);

        $this->assertSame('parent-uuid', $child->getParent()?->getId());
        $this->assertSame('Bougies', $child->getParent()->getName());
        $this->assertSame($parentEntity, $entity->getParent());
    }

    public function testTheParentOfTheParentIsNeverRead(): void
    {
        // A row that broke the two levels (or a cycle) must not make the reads fail.
        $grandParent = new DoctrineCategory('grand-uuid', 'Maison', 'maison');
        $parentEntity = new DoctrineCategory('parent-uuid', 'Bougies', 'bougies', $grandParent);
        $grandParent->setParent($parentEntity);

        $child = $this->mapper->toDomain(new DoctrineCategory('cat-uuid', 'Fondants', 'fondants', $parentEntity));

        $this->assertSame('parent-uuid', $child->getParent()?->getId());
        $this->assertTrue($child->getParent()->isTopLevel());
    }

    public function testMapDomainToEntityCopiesTheNameTheSlugAndTheParent(): void
    {
        $entity = new DoctrineCategory('cat-uuid', 'Fondant', 'fondant', new DoctrineCategory('old-parent', 'Maison', 'maison'));

        $this->mapper->mapDomainToEntity(new Category('cat-uuid', 'Fondants', 'fondants'), $entity, null);

        $this->assertSame('Fondants', $entity->getName());
        $this->assertSame('fondants', $entity->getSlug());
        $this->assertNull($entity->getParent());
    }
}
