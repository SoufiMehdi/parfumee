<?php

namespace App\Ecommerce\Infrastructure\Controller\Catalog;  

use App\Ecommerce\Application\UseCase\Catalog\CreateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetFilteredProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Infrastructure\Presenter\Catalog\ProductPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;
use App\Ecommerce\Application\DTO\Catalog\UpdateProductDto;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;
use App\Ecommerce\Application\UseCase\Catalog\UploadProductImageUseCase;

class ProductController extends AbstractController
{
    private const string PICTURE_MAX_SIZE = '5M';
    private const array PICTURE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    #[Route('/products', name: 'get_products', methods: [Request::METHOD_GET])]
    public function get(GetProductsUseCase $useCase, ProductPresenter $presenter): JsonResponse
    {
        $products = $useCase->execute();
        $data = $presenter->presentCollection($products);
        return new JsonResponse($data);
    }

    #[Route('/product/{id}', name: 'get_product', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_GET])]
    public function getById(string $id, GetProductUseCase $useCase, ProductPresenter $presenter): JsonResponse
    {
        $data = $presenter->present($useCase->execute($id));
        return new JsonResponse($data);
    }

    #[Route('/products/filter', name: 'get_filtered_products', methods: [Request::METHOD_GET])]
    public function getFiltered(
        GetFilteredProductsUseCase $useCase,
        ProductPresenter $presenter,
        // 422 like any other invalid input: the attribute answers 404 by default.
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        GetProductsFilterDto $filter = new GetProductsFilterDto(),
    ): JsonResponse {
        return new JsonResponse($presenter->presentCollection($useCase->execute($filter)));
    }

    #[Route('/create-product', name: 'create_product', methods: [Request::METHOD_POST])]
    public function create(#[MapRequestPayload] CreateProductDto $dto, CreateProductUseCase $useCase): JsonResponse
    {
        return new JsonResponse($useCase->execute($dto), JsonResponse::HTTP_CREATED);
    }

    #[Route('/edit-product/{id}', name: 'update_product', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_PUT])]
    public function update(string $id, #[MapRequestPayload] UpdateProductDto $dto, UpdateProductUseCase $useCase): JsonResponse
    {
        $product = $useCase->execute($dto, $id);

        return new JsonResponse([
            'result' => 'Product updated successfully',
            'data' => $product->getId(),
        ]);
    }

    #[Route('/upload-picture/{productId}', name: 'upload_picture', requirements: ['productId' => Requirement::UUID], methods: [Request::METHOD_POST])]
    public function uploadPicture(
        string $productId,
        Request $request,
        UploadProductImageUseCase $useCase,
        // The type is read from the file content, never from the name or the
        // Content-Type sent by the client. SVG is refused on purpose: it can
        // carry JavaScript and would be served from public/uploads (stored XSS).
        #[MapUploadedFile([
            new Assert\Image(
                maxSize: self::PICTURE_MAX_SIZE,
                mimeTypes: self::PICTURE_MIME_TYPES,
                mimeTypesMessage: 'The picture must be a JPEG, PNG or WebP image.',
            ),
        ])]
        ?UploadedFile $file = null,
    ): JsonResponse {
        $alt = $request->request->get('alt');

        if (!$file) {
            throw new BadRequestHttpException('The "file" field is required.');
        }

        $picture = $useCase->execute($productId, $file, $alt);

        return new JsonResponse([
            'result' => 'Image uploaded successfully',
            'data' => $picture->getId(),
        ]);
    }
}
