<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Shop;

use App\Ecommerce\Application\DTO\Catalog\ListProductsQueryDto;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\ListProductsUseCase;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Infrastructure\Presenter\Catalog\ProductPresenter;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\ProductListView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\ProductView;
use App\Ecommerce\Infrastructure\Presenter\Common\PaginationView;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Catalogue as the shop sees it: read-only, public, and made of the published products only.
 */
#[Route('/shop/products', name: 'shop_products_')]
#[OA\Tag(name: 'Shop · Catalogue')]
#[Security(name: null)]
class ProductController extends AbstractController
{
    #[Route('', name: 'list', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'List the products', description: 'One page of the published products, sorted by name unless told otherwise. Every filter is optional.')]
    #[OA\Response(response: 200, description: 'One page of the matching products', headers: [new OA\Header(header: PaginationView::TOTAL_COUNT_HEADER, ref: '#/components/headers/TotalCount')], content: new Model(type: ProductListView::class))]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    public function list(
        ListProductsUseCase $useCase,
        ProductPresenter $presenter,
        // 422 like any other invalid input: the attribute answers 404 by default.
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListProductsQueryDto $query = new ListProductsQueryDto(),
    ): JsonResponse {
        $list = $presenter->presentPage($useCase->execute($query, [ProductStatus::PUBLISHED]));

        return $this->json($list, headers: $list->pagination->headers());
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Read a product', description: 'A product that is not published is not found.')]
    #[OA\Response(response: 200, description: 'The product', content: new Model(type: ProductView::class))]
    #[OA\Response(response: 404, ref: '#/components/responses/NotFound')]
    public function get(string $id, GetProductUseCase $useCase, ProductPresenter $presenter): JsonResponse
    {
        return $this->json($presenter->present($useCase->execute($id, [ProductStatus::PUBLISHED])));
    }
}
