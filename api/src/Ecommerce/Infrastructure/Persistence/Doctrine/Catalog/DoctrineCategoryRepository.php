<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Domain\Exception\Catalog\CategoryNotEmptyException;
use App\Ecommerce\Domain\Exception\Catalog\CategoryNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Catalog\CategoryCriteria;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\CategorySortField;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Infrastructure\Persistence\Doctrine\SqlOrderBy;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\CategoryMapper;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryMapper $mapper,
    ) {
    }

    public function save(Category $category): void
    {
        $parentId = $category->getParent()?->getId();
        $parent = null === $parentId ? null : $this->entityManager->getReference(DoctrineCategory::class, $parentId);

        $entity = $this->entityManager->find(DoctrineCategory::class, $category->getId());
        if (null === $entity) {
            $this->entityManager->persist($this->mapper->toInfrastructure($category, $parent));
        } else {
            $this->mapper->mapDomainToEntity($category, $entity, $parent);
        }

        $this->entityManager->flush();
    }

    public function delete(Category $category): void
    {
        $entity = $this->entityManager->find(DoctrineCategory::class, $category->getId())
            ?? throw new CategoryNotFoundException($category->getId());

        $this->entityManager->remove($entity);
        try {
            $this->entityManager->flush();
        } catch (ForeignKeyConstraintViolationException) {
            // Something was filed under it since the use case checked: 409, not 500.
            throw CategoryNotEmptyException::meanwhile($category->getId());
        }
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

    /**
     * @return Category[]
     */
    public function findAll(): array
    {
        $doctrineCategories = $this->entityManager->getRepository(DoctrineCategory::class)->findAll();

        return array_map([$this->mapper, 'toDomain'], $doctrineCategories);
    }

    public function findPage(CategoryCriteria $criteria, Pagination $pagination): Page
    {
        // SQL rather than DQL for the French sort, which DQL cannot express:
        // the ids of the page are found in SQL, then loaded in DQL.
        $conditions = [];
        $parameters = [];
        if (null !== $criteria->search) {
            $conditions[] = 'c.name ILIKE :search';
            // What the user typed is searched as such: % and _ are not wildcards.
            $parameters['search'] = '%'.addcslashes($criteria->search, '\\%_').'%';
        }
        if (null !== $criteria->parentId) {
            $conditions[] = 'c.parent_id = :parentId';
            $parameters['parentId'] = $criteria->parentId;
        }
        if ($criteria->topLevelOnly) {
            $conditions[] = 'c.parent_id IS NULL';
        }
        $where = [] === $conditions ? '' : ' WHERE '.implode(' AND ', $conditions);

        $sortColumn = match ($criteria->sortBy) {
            CategorySortField::NAME => SqlOrderBy::frenchText('c.name'),
            CategorySortField::CREATED_AT => 'c.created_at',
        };

        $connection = $this->entityManager->getConnection();
        $total = (int) $connection->fetchOne('SELECT COUNT(*) FROM categories c'.$where, $parameters);
        $ids = array_map(strval(...), $connection->fetchFirstColumn(
            'SELECT c.id FROM categories c'.$where
                .' ORDER BY '.SqlOrderBy::clause($sortColumn, $criteria->direction, 'c.id')
                .' LIMIT :limit OFFSET :offset',
            $parameters + ['limit' => $pagination->perPage, 'offset' => $pagination->offset()],
            ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
        ));

        return new Page($this->loadInOrder($ids), $total, $pagination);
    }

    public function slugExists(string $slug, ?string $exceptCategoryId = null): bool
    {
        $doctrineCategory = $this->entityManager->getRepository(DoctrineCategory::class)->findOneBy(['slug' => $slug]);

        return null !== $doctrineCategory && $doctrineCategory->getId() !== $exceptCategoryId;
    }

    public function hasChildren(string $id): bool
    {
        return false !== $this->entityManager->getConnection()
            ->fetchOne('SELECT 1 FROM categories WHERE parent_id = :id LIMIT 1', ['id' => $id]);
    }

    public function hasProducts(string $id): bool
    {
        return false !== $this->entityManager->getConnection()
            ->fetchOne('SELECT 1 FROM products WHERE category_id = :id LIMIT 1', ['id' => $id]);
    }

    /**
     * The categories of the page with their parent, in one query, in the order of $ids.
     *
     * @param list<string> $ids
     *
     * @return list<Category>
     */
    private function loadInOrder(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $entities = $this->entityManager
            ->createQuery('SELECT c, parent FROM '.DoctrineCategory::class.' c LEFT JOIN c.parent parent WHERE c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getResult();

        $byId = [];
        foreach ($entities as $entity) {
            if ($entity instanceof DoctrineCategory) {
                $byId[$entity->getId()] = $entity;
            }
        }

        $categories = [];
        foreach ($ids as $id) {
            // A category deleted between the two queries is left out of the page.
            if (isset($byId[$id])) {
                $categories[] = $this->mapper->toDomain($byId[$id]);
            }
        }

        return $categories;
    }
}
