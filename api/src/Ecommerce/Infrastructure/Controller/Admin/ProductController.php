<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Admin;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;
use App\Ecommerce\Application\DTO\Catalog\UpdateProductDto;
use App\Ecommerce\Application\UseCase\Catalog\CreateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetFilteredProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UploadProductImageUseCase;
use App\Ecommerce\Infrastructure\Presenter\Catalog\ProductPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Catalogue management of the back-office. The whole /api/admin space requires ROLE_ADMIN.
 */
#[Route('/api/admin/products', name: 'admin_products_')]
class ProductController extends AbstractController
{
    private const string PICTURE_MAX_SIZE = '5M';
    private const array PICTURE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    #[Route('', name: 'list', methods: [Request::METHOD_GET])]
    public function list(
        GetFilteredProductsUseCase $useCase,
        ProductPresenter $presenter,
        // 422 like any other invalid input: the attribute answers 404 by default.
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        GetProductsFilterDto $filter = new GetProductsFilterDto(),
    ): JsonResponse {
        return new JsonResponse($presenter->presentCollection($useCase->execute($filter)));
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_GET])]
    public function get(string $id, GetProductUseCase $useCase, ProductPresenter $presenter): JsonResponse
    {
        return new JsonResponse($presenter->present($useCase->execute($id)));
    }

    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
    public function create(#[MapRequestPayload] CreateProductDto $dto, CreateProductUseCase $useCase): JsonResponse
    {
        return new JsonResponse($useCase->execute($dto), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_PUT])]
    public function update(string $id, #[MapRequestPayload] UpdateProductDto $dto, UpdateProductUseCase $useCase): JsonResponse
    {
        $product = $useCase->execute($dto, $id);

        return new JsonResponse([
            'result' => 'Product updated successfully',
            'data' => $product->getId(),
        ]);
    }

    #[Route('/{id}/pictures', name: 'upload_picture', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_POST])]
    public function uploadPicture(
        string $id,
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

        $picture = $useCase->execute($id, $file, $alt);

        return new JsonResponse([
            'result' => 'Image uploaded successfully',
            'data' => $picture->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
