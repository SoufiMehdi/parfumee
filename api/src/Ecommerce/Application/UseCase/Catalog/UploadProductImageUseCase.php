<?php

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Storage\FileStorageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;
use App\Ecommerce\Domain\Repository\Catalog\PictureRepositoryInterface;

class UploadProductImageUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private FileStorageInterface $fileStorage,
        private PictureRepositoryInterface $pictureRepository
    ) {
    }

    public function execute(string $productId, UploadedFile $file, ?string $alt = null): Picture
    {
        // 1. On vérifie si le produit existe
        $product = $this->productRepository->findById($productId);
        if (!$product) {
            throw new \Exception("Produit non trouvé");
        }

        // 2. On upload le fichier physiquement via le Port
        // Le chemin retourné sera stocké en base
        $filePath = $this->fileStorage->store($file, 'products');

        // 3. On crée l'objet Domaine Picture
        $image = new Picture(
            Uuid::v4()->toRfc4122(),
            $filePath,
            $alt,
            product: $product
        );

        // 4. On l'ajoute au produit et on sauvegarde

        $product->addPicture($image);
        $this->productRepository->save($product);
        $this->pictureRepository->save($image);

        return $image;
    }
}