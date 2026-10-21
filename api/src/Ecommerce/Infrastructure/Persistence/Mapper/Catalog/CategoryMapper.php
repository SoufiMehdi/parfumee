<?php

namespace App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;

class CategoryMapper
{
    public function toInfrastructure(Category $category): DoctrineCategory
    {
        return new DoctrineCategory(
            $category->getId(),
            $category->getName(),
            $category->getSlug()
        );
    }

    public function toDomain(DoctrineCategory $doctrineCategory): Category
    {
        return new Category(
            $doctrineCategory->getId(),
            $doctrineCategory->getName(),
            $doctrineCategory->getSlug()
        );
    }

}