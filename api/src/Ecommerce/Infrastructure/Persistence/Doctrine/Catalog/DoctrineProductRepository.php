<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\ProductCriteria;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\ProductSortField;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Infrastructure\Persistence\Doctrine\SqlOrderBy;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\ProductMapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineProductRepository implements ProductRepositoryInterface
{
    private EntityManagerInterface $entityManager;
    private ProductMapper $mapper;

    public function __construct(EntityManagerInterface $entityManager, ProductMapper $mapper)
    {
        $this->entityManager = $entityManager;
        $this->mapper = $mapper;
    }

    public function save(Product $product): void
    {
        $doctrineProduct = $this->entityManager->find(DoctrineProduct::class, $product->getId());
        if (!$doctrineProduct) {
            // C'est un nouveau produit (Logique de création)
            $doctrineCategory = $this->entityManager->getReference(DoctrineCategory::class, $product->getCategory()->getId());
            $doctrineProduct = $this->mapper->toInfrastructure($product, $doctrineCategory);
            $this->entityManager->persist($doctrineProduct);
        } else {
            // C'est une mise à jour (Logique d'update)
            $doctrineCategory = $this->entityManager->getReference(DoctrineCategory::class, $product->getCategory()->getId());
            // Tu ajoutes une méthode dans ton Mapper pour mettre à jour l'entité existante
            $this->mapper->mapDomainToEntity($product, $doctrineProduct, $doctrineCategory);
        }

        // On persiste l'entité Doctrine
        $this->entityManager->persist($doctrineProduct);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?Product
    {
        $doctrineProduct = $this->entityManager->getRepository(DoctrineProduct::class)->find($id);

        return $doctrineProduct ? $this->mapper->toDomain($doctrineProduct) : null;
    }

    /**
     * @return Product[]
     */
    public function findAll(): array
    {
        $doctrineProducts = $this->entityManager->getRepository(DoctrineProduct::class)->findAll();

        return array_map([$this->mapper, 'toDomain'], $doctrineProducts);
    }

    public function slugExists(string $slug, ?string $exceptProductId = null): bool
    {
        $doctrineProduct = $this->entityManager->getRepository(DoctrineProduct::class)->findOneBy(['slug' => $slug]);

        return null !== $doctrineProduct && $doctrineProduct->getId() !== $exceptProductId;
    }

    /**
     * @return Page<Product>
     */
    public function findPage(ProductCriteria $criteria, Pagination $pagination): Page
    {
        // The filters on the JSON attributes and the French sort need PostgreSQL features
        // that DQL does not have: the ids of the page are found in SQL, then loaded in DQL.
        $conditions = [];
        $parameters = [];
        if (null !== $criteria->categoryId) {
            $conditions[] = 'p.category_id = :categoryId';
            $parameters['categoryId'] = $criteria->categoryId;
        }
        if (null !== $criteria->minPrice) {
            $conditions[] = 'p.price >= :minPrice';
            $parameters['minPrice'] = $criteria->minPrice;
        }
        if (null !== $criteria->maxPrice) {
            $conditions[] = 'p.price <= :maxPrice';
            $parameters['maxPrice'] = $criteria->maxPrice;
        }
        if (null !== $criteria->fragrance) {
            $conditions[] = "p.attributes->>'fragrance' = :fragrance";
            $parameters['fragrance'] = $criteria->fragrance;
        }
        if (null !== $criteria->size) {
            $conditions[] = "p.attributes->>'size' = :size";
            $parameters['size'] = $criteria->size;
        }
        $where = [] === $conditions ? '' : ' WHERE '.implode(' AND ', $conditions);

        $sortColumn = match ($criteria->sortBy) {
            ProductSortField::NAME => SqlOrderBy::frenchText('p.name'),
            ProductSortField::PRICE => 'p.price',
            ProductSortField::CREATED_AT => 'p.created_at',
        };

        $connection = $this->entityManager->getConnection();
        $total = (int) $connection->fetchOne('SELECT COUNT(*) FROM products p'.$where, $parameters);
        $ids = array_map(strval(...), $connection->fetchFirstColumn(
            'SELECT p.id FROM products p'.$where
                .' ORDER BY '.SqlOrderBy::clause($sortColumn, $criteria->direction, 'p.id')
                .' LIMIT :limit OFFSET :offset',
            $parameters + ['limit' => $pagination->perPage, 'offset' => $pagination->offset()],
            ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
        ));

        return new Page($this->loadInOrder($ids), $total, $pagination);
    }

    /**
     * Loads the products of a page, with their category and pictures, in one query,
     * and keeps the order of the ids.
     *
     * @param list<string> $ids
     *
     * @return list<Product>
     */
    private function loadInOrder(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $entities = $this->entityManager
            ->createQuery('SELECT p, c, pic FROM '.DoctrineProduct::class.' p JOIN p.category c LEFT JOIN p.pictures pic WHERE p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getResult();

        $byId = [];
        foreach ($entities as $entity) {
            if ($entity instanceof DoctrineProduct) {
                $byId[$entity->getId()] = $entity;
            }
        }

        $products = [];
        foreach ($ids as $id) {
            // A product deleted between the two queries is left out of the page.
            if (isset($byId[$id])) {
                $products[] = $this->mapper->toDomain($byId[$id]);
            }
        }

        return $products;
    }
}
