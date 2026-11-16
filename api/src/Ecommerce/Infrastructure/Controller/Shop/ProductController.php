<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Shop;

use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;
use App\Ecommerce\Application\UseCase\Catalog\GetFilteredProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Infrastructure\Presenter\Catalog\ProductPresenter;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\ProductView;
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
 * Catalogue as the shop sees it: read-only and public.
 */
#[Route('/shop/products', name: 'shop_products_')]
#[OA\Tag(name: 'Shop · Catalogue')]
#[Security(name: null)]
class ProductController extends AbstractController
{
    #[Route('', name: 'list', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'List the products', description: 'Every filter is optional: without any, the whole catalogue is returned.')]
    #[OA\Response(response: 200, description: 'The matching products', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: ProductView::class))))]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    public function list(
        GetFilteredProductsUseCase $useCase,
        ProductPresenter $presenter,
        // 422 like any other invalid input: the attribute answers 404 by default.
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        GetProductsFilterDto $filter = new GetProductsFilterDto(),
    ): JsonResponse {
        return $this->json($presenter->presentCollection($useCase->execute($filter)));
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Read a product')]
    #[OA\Response(response: 200, description: 'The product', content: new Model(type: ProductView::class))]
    #[OA\Response(response: 404, ref: '#/components/responses/NotFound')]
    public function get(string $id, GetProductUseCase $useCase, ProductPresenter $presenter): JsonResponse
    {
        return $this->json($presenter->present($useCase->execute($id)));
    }
}
