<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Admin;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\DTO\Catalog\ListProductsQueryDto;
use App\Ecommerce\Application\DTO\Catalog\ProductStatusFilterDto;
use App\Ecommerce\Application\DTO\Catalog\UpdateProductDto;
use App\Ecommerce\Application\UseCase\Catalog\ArchiveProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\CreateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\ListProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UploadProductImageUseCase;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Infrastructure\Presenter\Catalog\ProductPresenter;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\ProductListView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\ProductView;
use App\Ecommerce\Infrastructure\Presenter\Common\IdView;
use App\Ecommerce\Infrastructure\Presenter\Common\PaginationView;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
 * Catalogue management of the back-office. Requires ROLE_EDITOR.
 */
#[Route('/admin/products', name: 'admin_products_')]
#[OA\Tag(name: 'Back-office · Catalogue')]
#[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
#[OA\Response(response: 403, ref: '#/components/responses/Forbidden')]
class ProductController extends AbstractController
{
    private const string PICTURE_MAX_SIZE = '5M';
    private const array PICTURE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    #[Route('', name: 'list', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'List the products', description: 'Requires the editor role. One page of the catalogue, sorted by name unless told otherwise. Every filter is optional; without a status, the archived products are left out.')]
    #[OA\Response(response: 200, description: 'One page of the matching products', headers: [new OA\Header(header: PaginationView::TOTAL_COUNT_HEADER, ref: '#/components/headers/TotalCount')], content: new Model(type: ProductListView::class))]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    public function list(
        ListProductsUseCase $useCase,
        ProductPresenter $presenter,
        // 422 like any other invalid input: the attribute answers 404 by default.
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListProductsQueryDto $query = new ListProductsQueryDto(),
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ProductStatusFilterDto $filter = new ProductStatusFilterDto(),
    ): JsonResponse {
        $list = $presenter->presentPage($useCase->execute($query, $filter->statuses()));

        return $this->json($list, headers: $list->pagination->headers());
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Read a product', description: 'Requires the editor role. Whatever its status, archived included.')]
    #[OA\Response(response: 200, description: 'The product', content: new Model(type: ProductView::class))]
    #[OA\Response(response: 404, ref: '#/components/responses/NotFound')]
    public function get(string $id, GetProductUseCase $useCase, ProductPresenter $presenter): JsonResponse
    {
        return $this->json($presenter->present($useCase->execute($id, ProductStatus::cases())));
    }

    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Create a product', description: 'Requires the editor role. The slug is computed from the name. A product is a draft unless created with another status.')]
    #[OA\Response(response: 201, description: 'The product is created', content: new Model(type: IdView::class))]
    #[OA\Response(response: 400, ref: '#/components/responses/BadRequest')]
    #[OA\Response(response: 404, description: 'The category does not exist', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    public function create(#[MapRequestPayload] CreateProductDto $dto, CreateProductUseCase $useCase): JsonResponse
    {
        return $this->json(new IdView((string) $useCase->execute($dto)), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_PUT])]
    #[OA\Put(summary: 'Update a product', description: 'Requires the editor role. The slug follows the name. Without a status, the product keeps its own.')]
    #[OA\Response(response: 200, description: 'The updated product', content: new Model(type: ProductView::class))]
    #[OA\Response(response: 400, ref: '#/components/responses/BadRequest')]
    #[OA\Response(response: 404, description: 'The product or the category does not exist', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateProductDto $dto,
        UpdateProductUseCase $useCase,
        ProductPresenter $presenter,
    ): JsonResponse {
        return $this->json($presenter->present($useCase->execute($dto, $id)));
    }

    #[Route('/{id}', name: 'archive', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_DELETE])]
    #[OA\Delete(summary: 'Archive a product', description: 'Requires the editor role. The product leaves the shop but is kept, with the status "archived": the orders that sold it still read it. Archiving an archived product changes nothing.')]
    #[OA\Response(response: 204, description: 'The product is archived')]
    #[OA\Response(response: 404, ref: '#/components/responses/NotFound')]
    public function archive(string $id, ArchiveProductUseCase $useCase): Response
    {
        $useCase->execute($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/pictures', name: 'upload_picture', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Add a picture to a product', description: 'Requires the editor role. JPEG, PNG or WebP, 5 MB at most.')]
    #[OA\RequestBody(content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(
        required: ['file'],
        properties: [
            new OA\Property(property: 'file', type: 'string', format: 'binary'),
            new OA\Property(property: 'alt', description: 'Alternative text', type: 'string'),
        ],
    )))]
    #[OA\Response(response: 201, description: 'The picture is stored', content: new Model(type: IdView::class))]
    #[OA\Response(response: 400, ref: '#/components/responses/BadRequest')]
    #[OA\Response(response: 404, ref: '#/components/responses/NotFound')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
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

        return $this->json(new IdView($picture->getId()), JsonResponse::HTTP_CREATED);
    }
}
