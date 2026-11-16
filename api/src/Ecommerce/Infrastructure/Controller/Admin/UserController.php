<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Admin;

use App\Ecommerce\Application\DTO\User\UpdateUserRequestDto;
use App\Ecommerce\Application\UseCase\User\DeleteUserUseCase;
use App\Ecommerce\Application\UseCase\User\GetUsersUseCase;
use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Application\UseCase\User\UpdateUserUseCase;
use App\Ecommerce\Infrastructure\Presenter\User\UserPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Customer accounts as the back-office sees them: any account, not only one's own.
 * The whole /api/admin space requires ROLE_ADMIN.
 */
#[Route('/api/admin/users', name: 'admin_users_')]
class UserController extends AbstractController
{
    #[Route('', name: 'list', methods: [Request::METHOD_GET])]
    public function list(GetUsersUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        return new JsonResponse($presenter->presentCollection($useCase->execute()));
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_GET])]
    public function get(string $id, GetUserUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        return new JsonResponse($presenter->present($useCase->execute($id)));
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_PUT])]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateUserRequestDto $dto,
        UpdateUserUseCase $useCase,
        UserPresenter $presenter,
    ): JsonResponse {
        return new JsonResponse($presenter->present($useCase->execute($id, $dto)));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_DELETE])]
    public function delete(string $id, DeleteUserUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return new JsonResponse(['message' => 'User deleted successfully']);
    }
}
