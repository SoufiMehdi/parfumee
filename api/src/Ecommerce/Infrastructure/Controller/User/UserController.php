<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\User;

use App\Ecommerce\Application\Mapper\User\LoginUserRequestMapper;
use App\Ecommerce\Application\Mapper\User\RegisterUserRequestMapper;
use App\Ecommerce\Application\Mapper\User\UpdateUserRequestMapper;
use App\Ecommerce\Application\UseCase\User\DeleteUserUseCase;
use App\Ecommerce\Application\UseCase\User\GetUsersUseCase;
use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use App\Ecommerce\Application\UseCase\User\RegisterUserUseCase;
use App\Ecommerce\Application\UseCase\User\UpdateUserUseCase;
use App\Ecommerce\Infrastructure\Presenter\User\UserPresenter;
use App\Ecommerce\Infrastructure\Security\Voter\UserAccountVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * No try/catch here: errors are turned into responses by ExceptionListener.
 */
#[Route('/users')]
class UserController extends AbstractController
{
    #[Route('/login', name: 'login_user', methods: [Request::METHOD_POST])]
    public function login(Request $request, LoginUserUseCase $useCase): JsonResponse
    {
        return new JsonResponse($useCase->execute(LoginUserRequestMapper::fromRequest($request)));
    }

    #[Route('/list', name: 'get_users', methods: [Request::METHOD_GET])]
    public function getList(GetUsersUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        $users = $useCase->execute();
        $data = $presenter->presentCollection($users);

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'get_user', methods: [Request::METHOD_GET])]
    #[IsGranted(UserAccountVoter::ACCESS, subject: 'id', message: 'Access denied.')]
    public function getById(string $id, GetUserUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        return new JsonResponse($presenter->present($useCase->execute($id)));
    }

    #[Route('/create', name: 'create_user', methods: [Request::METHOD_POST])]
    public function create(Request $request, RegisterUserUseCase $useCase): JsonResponse
    {
        $useCase->execute(RegisterUserRequestMapper::fromRequest($request));

        return new JsonResponse(['message' => 'User created successfully'], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update_user', methods: [Request::METHOD_PUT])]
    #[IsGranted(UserAccountVoter::ACCESS, subject: 'id', message: 'Access denied.')]
    public function update(
        string $id,
        Request $request,
        UpdateUserUseCase $useCase,
        UserPresenter $presenter,
    ): JsonResponse {
        $user = $useCase->execute($id, UpdateUserRequestMapper::fromRequest($request));

        return new JsonResponse($presenter->present($user));
    }

    #[Route('/{id}', name: 'delete_user', methods: [Request::METHOD_DELETE])]
    #[IsGranted(UserAccountVoter::ACCESS, subject: 'id', message: 'Access denied.')]
    public function delete(string $id, DeleteUserUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return new JsonResponse(['message' => 'User deleted successfully']);
    }
}
