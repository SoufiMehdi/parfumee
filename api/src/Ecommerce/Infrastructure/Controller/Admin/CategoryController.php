<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Admin;

use App\Ecommerce\Application\DTO\Catalog\CreateCategoryDto;
use App\Ecommerce\Application\DTO\Catalog\ListCategoriesQueryDto;
use App\Ecommerce\Application\DTO\Catalog\UpdateCategoryDto;
use App\Ecommerce\Application\UseCase\Catalog\CreateCategoryUseCase;
use App\Ecommerce\Application\UseCase\Catalog\DeleteCategoryUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetCategoryUseCase;
use App\Ecommerce\Application\UseCase\Catalog\ListCategoriesUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UpdateCategoryUseCase;
use App\Ecommerce\Infrastructure\Presenter\Catalog\CategoryPresenter;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\CategoryListView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\CategoryView;
use App\Ecommerce\Infrastructure\Presenter\Common\IdView;
use App\Ecommerce\Infrastructure\Presenter\Common\PaginationView;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Categories of the back-office. Requires ROLE_EDITOR. Two levels: a top-level category
 * holds child categories, which hold none.
 */
#[Route('/admin/categories', name: 'admin_categories_')]
#[OA\Tag(name: 'Back-office · Catalogue')]
#[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
#[OA\Response(response: 403, ref: '#/components/responses/Forbidden')]
class CategoryController extends AbstractController
{
    #[Route('', name: 'list', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'List the categories', description: 'Requires the editor role. One page of the categories, sorted by name unless told otherwise. Every filter is optional.')]
    #[OA\Response(response: 200, description: 'One page of the matching categories', headers: [new OA\Header(header: PaginationView::TOTAL_COUNT_HEADER, ref: '#/components/headers/TotalCount')], content: new Model(type: CategoryListView::class))]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    public function list(
        ListCategoriesUseCase $useCase,
        CategoryPresenter $presenter,
        // 422 like any other invalid input: the attribute answers 404 by default.
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListCategoriesQueryDto $query = new ListCategoriesQueryDto(),
    ): JsonResponse {
        $list = $presenter->presentPage($useCase->execute($query));

        return $this->json($list, headers: $list->pagination->headers());
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Read a category', description: 'Requires the editor role.')]
    #[OA\Response(response: 200, description: 'The category', content: new Model(type: CategoryView::class))]
    #[OA\Response(response: 404, ref: '#/components/responses/NotFound')]
    public function get(string $id, GetCategoryUseCase $useCase, CategoryPresenter $presenter): JsonResponse
    {
        return $this->json($presenter->present($useCase->execute($id)));
    }

    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Create a category', description: 'Requires the editor role. The slug is computed from the name. With parent_id, the category is filed under that top-level category.')]
    #[OA\Response(response: 201, description: 'The category is created', content: new Model(type: IdView::class))]
    #[OA\Response(response: 400, ref: '#/components/responses/BadRequest')]
    #[OA\Response(response: 404, description: 'The parent category does not exist', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'A field is invalid (with violations), or the parent is itself a child category (without)', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: '#/components/schemas/ValidationError'), new OA\Schema(ref: '#/components/schemas/Error')]))]
    public function create(#[MapRequestPayload] CreateCategoryDto $dto, CreateCategoryUseCase $useCase): JsonResponse
    {
        return $this->json(new IdView($useCase->execute($dto)), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_PUT])]
    #[OA\Put(summary: 'Update a category', description: 'Requires the editor role. The slug follows the name. Without parent_id, the category becomes a top-level one.')]
    #[OA\Response(response: 200, description: 'The updated category', content: new Model(type: CategoryView::class))]
    #[OA\Response(response: 400, ref: '#/components/responses/BadRequest')]
    #[OA\Response(response: 404, description: 'The category or its parent does not exist', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'A field is invalid (with violations), or the parent breaks the two levels (itself, a child category, or a category that holds others going under one)', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: '#/components/schemas/ValidationError'), new OA\Schema(ref: '#/components/schemas/Error')]))]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateCategoryDto $dto,
        UpdateCategoryUseCase $useCase,
        CategoryPresenter $presenter,
    ): JsonResponse {
        return $this->json($presenter->present($useCase->execute($id, $dto)));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_DELETE])]
    #[OA\Delete(summary: 'Delete a category', description: 'Requires the editor role. Only an empty category is deleted: one that holds a child category or a product (archived ones included) answers 409.')]
    #[OA\Response(response: 204, description: 'The category is deleted')]
    #[OA\Response(response: 404, ref: '#/components/responses/NotFound')]
    #[OA\Response(response: 409, ref: '#/components/responses/Conflict')]
    public function delete(string $id, DeleteCategoryUseCase $useCase): Response
    {
        $useCase->execute($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
