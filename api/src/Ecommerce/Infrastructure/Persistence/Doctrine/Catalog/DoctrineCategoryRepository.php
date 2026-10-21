<?php
namespace App\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\CategoryMapper;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineCategoryRepository implements CategoryRepositoryInterface
{
    private EntityManagerInterface $entityManager;
    private CategoryMapper $mapper;

    public function __construct(EntityManagerInterface $entityManager, CategoryMapper $mapper)
    {
        $this->entityManager = $entityManager;
        $this->mapper = $mapper;
    }

    public function save(Category $category): void
    {
        $doctrineCategory = $this->mapper->toInfrastructure($category);
        $this->entityManager->persist($doctrineCategory);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?Category
    {
        $doctrineCategory = $this->entityManager->getRepository(DoctrineCategory::class)->find($id);
        return $doctrineCategory ? $this->mapper->toDomain($doctrineCategory) : null;
    }

    public function findBySlug(string $slug): ?Category
    {
        $doctrineCategory = $this->entityManager->getRepository(DoctrineCategory::class)->findOneBy(['slug' => $slug]);
        return $doctrineCategory ? $this->mapper->toDomain($doctrineCategory) : null;
    }

    public function findAll(): array
    {
        $doctrineCategories = $this->entityManager->getRepository(DoctrineCategory::class)->findAll();
        return array_map([$this->mapper, 'toDomain'], $doctrineCategories);
    }
}