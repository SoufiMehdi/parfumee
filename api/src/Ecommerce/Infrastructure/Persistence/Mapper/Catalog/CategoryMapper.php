<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;

class CategoryMapper
{
    /**
     * @param DoctrineCategory|null $parent the entity of the parent of $category, found by the repository
     */
    public function toInfrastructure(Category $category, ?DoctrineCategory $parent = null): DoctrineCategory
    {
        return new DoctrineCategory(
            $category->getId(),
            $category->getName(),
            $category->getSlug(),
            $parent,
        );
    }

    /**
     * The dates are never copied back: Gedmo sets them when the entity is saved.
     */
    public function mapDomainToEntity(Category $category, DoctrineCategory $entity, ?DoctrineCategory $parent): void
    {
        $entity->setName($category->getName());
        $entity->setSlug($category->getSlug());
        $entity->setParent($parent);
    }

    /**
     * The parent comes along, one level only: with two levels, it is a top-level category.
     * Its own parent is never read, so that a row that broke the rule (which the database
     * refuses, see Version20261122165552) could not make every read of the category fail.
     */
    public function toDomain(DoctrineCategory $doctrineCategory): Category
    {
        $parent = $doctrineCategory->getParent();

        return new Category(
            $doctrineCategory->getId(),
            $doctrineCategory->getName(),
            $doctrineCategory->getSlug(),
            null === $parent ? null : $this->topLevel($parent),
            $doctrineCategory->getCreatedAt(),
            $doctrineCategory->getUpdatedAt(),
        );
    }

    private function topLevel(DoctrineCategory $doctrineCategory): Category
    {
        return new Category(
            $doctrineCategory->getId(),
            $doctrineCategory->getName(),
            $doctrineCategory->getSlug(),
            null,
            $doctrineCategory->getCreatedAt(),
            $doctrineCategory->getUpdatedAt(),
        );
    }
}
