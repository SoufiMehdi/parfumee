<?php

namespace App\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;
use App\Ecommerce\Domain\Exception\Catalog\CategorieNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\ProductMapper;
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

    public function findAll(): array
    {
        $doctrineProducts = $this->entityManager->getRepository(DoctrineProduct::class)->findAll();
        return array_map([$this->mapper, 'toDomain'], $doctrineProducts);
    }

    public function findByFilter(GetProductsFilterDto $filterDto): array
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMappingBuilder($this->entityManager);
        $rsm->addRootEntityFromClassMetadata(DoctrineProduct::class, 'p');

        $sql = "SELECT p.* FROM products p WHERE 1=1";
        $params = [];
        // 1. Filtre par catégorie
        if ($filterDto->categoryId) {
            $sql .= " AND p.category_id = :categoryId";
            $params['categoryId'] = $filterDto->categoryId;
        }

        // 2. Filtres par prix
        if ($filterDto->minPrice !== null) {
            $sql .= " AND p.price >= :minPrice";
            $params['minPrice'] = $filterDto->minPrice;
        }
        if ($filterDto->maxPrice !== null) {
            $sql .= " AND p.price <= :maxPrice";
            $params['maxPrice'] = $filterDto->maxPrice;
        }

        // 3. Filtres sur le JSON d'attributs (taille et parfum)
        // Si tes attributs sont stockés dans un champ JSON sous Doctrine :
        if ($filterDto->scent) {
            $sql .= " AND p.attributes->>'fragrance' = :scent";
            $params['scent'] = $filterDto->scent;
        }
        if ($filterDto->size) {
            $sql .= " AND p.attributes->>'size' = :size";
            $params['size'] = $filterDto->size;
        }

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        foreach ($params as $key => $value) {
            $query->setParameter($key, $value);
        }

        $entities = $query->getResult();

        // On transforme les entités Doctrine en modèles de Domaine via ton mapper
        return $entities ? array_map([$this->mapper, 'toDomain'], $entities) : [];
    }
}
