<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Shop;

use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;
use App\Ecommerce\Application\UseCase\Catalog\GetFilteredProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Infrastructure\Presenter\Catalog\ProductPresenter;
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
#[Route('/api/shop/products', name: 'shop_products_')]
class ProductController extends AbstractController
{
    #[Route('', name: 'list', methods: [Request::METHOD_GET])]
    public function list(
        GetFilteredProductsUseCase $useCase,
        ProductPresenter $presenter,
        // Every filter is optional: without any, the whole catalogue is returned.
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
}
