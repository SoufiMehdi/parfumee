<?php

namespace App\Ecommerce\Infrastructure\Controller\Catalog;  

use App\Ecommerce\Application\UseCase\Catalog\CreateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetFilteredProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Infrastructure\Presenter\Catalog\ProductPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\DTO\Catalog\UpdateProductDto;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use Symfony\Component\Routing\Attribute\Route;
use App\Ecommerce\Application\UseCase\Catalog\UploadProductImageUseCase;

class ProductController extends AbstractController
{
    #[Route('/products', name: 'get_products', methods: [Request::METHOD_GET])]
    public function get(GetProductsUseCase $useCase, ProductPresenter $presenter): JsonResponse
    {
        $products = $useCase->execute();
        $data = $presenter->presentCollection($products);
        return new JsonResponse($data);
    }

    #[Route('/product/{id}', name: 'get_product', methods: [Request::METHOD_GET])]
    public function getById(string $id, GetProductUseCase $useCase, ProductPresenter $presenter): JsonResponse
    {
        $product = $useCase->execute($id);
        if (!$product) {
            return new JsonResponse(['message' => 'Product not found'], JsonResponse::HTTP_NOT_FOUND);
        }
        $data = $presenter->present($product);
        return new JsonResponse($data);
    }

    #[Route('/products/filter', name: 'get_filtered_products', methods: [Request::METHOD_GET])]
    public function getFiltered(Request $request, GetFilteredProductsUseCase $useCase, ProductPresenter $presenter): JsonResponse
    {
        $products = $useCase->execute($request->query->all());
        $data = $presenter->presentCollection($products);

        return new JsonResponse($data);
    }

    #[Route('/create-product', name: 'create_product', methods: [Request::METHOD_POST])]
    public function create(Request $request, CreateProductUseCase $useCase): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $dto = new CreateProductDto(
            name: $data['name'] ?? '',
            price: $data['price'] ?? 0,
            categoryId: $data['category_id'] ?? '',
            attributes: $data['attributes'] ?? []
        );
        $product = $useCase->execute($dto);
        return new JsonResponse($product, JsonResponse::HTTP_CREATED);
    }
    #[Route('/edit-product/{id}', name: 'update_product', methods: [Request::METHOD_PUT])]
    public function update(string $id, Request $request, UpdateProductUseCase $useCase): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $dto = new UpdateProductDto(
            name: $data['name'] ?? '',
            price: $data['price'] ?? 0,
            categoryId: $data['category_id'] ?? '',
            attributes: $data['attributes'] ?? []
        );
        $product = $useCase->execute($dto, $id);
        return new JsonResponse(
            [
                'result' => 'Product updated successfully',
                'data' => $product->getId()
            ],
            JsonResponse::HTTP_OK
            );
    }
    #[Route('/upload-picture/{productId}', name: 'upload_picture', methods: [Request::METHOD_POST])]
    public function uploadPicture(string $productId, Request $request, UploadProductImageUseCase $useCase): JsonResponse
    {
        $file = $request->files->get('file');
        $alt = $request->request->get('alt');

        if (!$productId || !$file) {
            return new JsonResponse(['message' => 'Product ID and file are required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $picture = $useCase->execute($productId, $file, $alt);
            return new JsonResponse(
                [
                'result' => 'Image uploaded successfully',
                'data' => $picture->getId()
                ],
                JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
