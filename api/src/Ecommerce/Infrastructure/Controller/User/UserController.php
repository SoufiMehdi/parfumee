<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\User;

use App\Ecommerce\Application\UseCase\User\RegisterUserUseCase;
use App\Ecommerce\Application\UseCase\User\GetUsersUseCase;
use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Application\UseCase\User\UpdateUserUseCase;
use App\Ecommerce\Application\UseCase\User\DeleteUserUseCase;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use App\Ecommerce\Application\Mapper\User\UpdateUserRequestMapper;
use App\Ecommerce\Application\Mapper\User\LoginUserRequestMapper;
use App\Ecommerce\Domain\Exception\User\UserNotFoundException;
use App\Ecommerce\Infrastructure\Presenter\User\UserPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use App\Ecommerce\Application\Mapper\User\RegisterUserRequestMapper;

#[Route('/users')]
class UserController extends AbstractController
{
    #[Route('/login', name: 'login_user', methods: [Request::METHOD_POST])]
    public function login(
        Request $request,
        LoginUserUseCase $useCase
    ): JsonResponse {
        try {
            $dto = LoginUserRequestMapper::fromRequest($request);
            $result = $useCase->execute($dto);

            return new JsonResponse($result);
        } catch (AuthenticationException $e) {
            return new JsonResponse(
                ['message' => $e->getMessage()],
                JsonResponse::HTTP_UNAUTHORIZED
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['message' => 'Invalid request data', 'error' => $e->getMessage()],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }
    }

    #[Route('/list', name: 'get_users', methods: [Request::METHOD_GET])]
    public function getList(GetUsersUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        $users = $useCase->execute();
        $data = $presenter->presentCollection($users);

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'get_user', methods: [Request::METHOD_GET])]
    public function getById(string $id, GetUserUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        try {
            $user = $useCase->execute($id);
            $data = $presenter->present($user);

            return new JsonResponse($data);
        } catch (UserNotFoundException $e) {
            return new JsonResponse(
                ['message' => $e->getMessage()],
                JsonResponse::HTTP_NOT_FOUND
            );
        }
    }

    #[Route('/create', name: 'create_user', methods: [Request::METHOD_POST])]
    public function create(
        Request $request,
        RegisterUserUseCase $useCase
    ): JsonResponse {
        try {
            $dto = RegisterUserRequestMapper::fromRequest($request);
            $useCase->execute($dto);

            return new JsonResponse(
                ['message' => 'User created successfully'],
                JsonResponse::HTTP_CREATED
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['message' => 'Invalid request data', 'error' => $e->getMessage()],
                JsonResponse::HTTP_BAD_REQUEST
            );
        } catch (\DomainException $e) {
            return new JsonResponse(
                ['message' => $e->getMessage()],
                JsonResponse::HTTP_CONFLICT
            );
        }
    }

    #[Route('/{id}', name: 'update_user', methods: [Request::METHOD_PUT])]
    public function update(
        string $id,
        Request $request,
        UpdateUserUseCase $useCase,
        UserPresenter $presenter
    ): JsonResponse {
        try {
            $dto = UpdateUserRequestMapper::fromRequest($request);
            $user = $useCase->execute($id, $dto);
            $data = $presenter->present($user);

            return new JsonResponse($data);
        } catch (UserNotFoundException $e) {
            return new JsonResponse(
                ['message' => $e->getMessage()],
                JsonResponse::HTTP_NOT_FOUND
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['message' => 'Invalid request data', 'error' => $e->getMessage()],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }
    }

    #[Route('/{id}', name: 'delete_user', methods: [Request::METHOD_DELETE])]
    public function delete(string $id, DeleteUserUseCase $useCase): JsonResponse
    {
        try {
            $useCase->execute($id);

            return new JsonResponse(
                ['message' => 'User deleted successfully'],
                JsonResponse::HTTP_OK
            );
        } catch (UserNotFoundException $e) {
            return new JsonResponse(
                ['message' => $e->getMessage()],
                JsonResponse::HTTP_NOT_FOUND
            );
        }
    }
}