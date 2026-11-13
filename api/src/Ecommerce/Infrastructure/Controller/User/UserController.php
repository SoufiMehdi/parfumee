<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\User;

use App\Ecommerce\Application\DTO\User\LoginUserRequestDto;
use App\Ecommerce\Application\DTO\User\RegisterUserRequestDto;
use App\Ecommerce\Application\DTO\User\UpdateUserRequestDto;
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
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * No try/catch and no manual validation here: the body is mapped and
 * validated by #[MapRequestPayload], and errors are turned into responses by
 * ExceptionListener.
 */
#[Route('/users')]
class UserController extends AbstractController
{
    #[Route('/login', name: 'login_user', methods: [Request::METHOD_POST])]
    public function login(#[MapRequestPayload] LoginUserRequestDto $dto, LoginUserUseCase $useCase): JsonResponse
    {
        return new JsonResponse($useCase->execute($dto));
    }

    #[Route('/list', name: 'get_users', methods: [Request::METHOD_GET])]
    public function getList(GetUsersUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        $users = $useCase->execute();
        $data = $presenter->presentCollection($users);

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'get_user', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_GET])]
    #[IsGranted(UserAccountVoter::ACCESS, subject: 'id', message: 'Access denied.')]
    public function getById(string $id, GetUserUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        return new JsonResponse($presenter->present($useCase->execute($id)));
    }

    #[Route('/create', name: 'create_user', methods: [Request::METHOD_POST])]
    public function create(#[MapRequestPayload] RegisterUserRequestDto $dto, RegisterUserUseCase $useCase): JsonResponse
    {
        $useCase->execute($dto);

        return new JsonResponse(['message' => 'User created successfully'], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update_user', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_PUT])]
    #[IsGranted(UserAccountVoter::ACCESS, subject: 'id', message: 'Access denied.')]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateUserRequestDto $dto,
        UpdateUserUseCase $useCase,
        UserPresenter $presenter,
    ): JsonResponse {
        return new JsonResponse($presenter->present($useCase->execute($id, $dto)));
    }

    #[Route('/{id}', name: 'delete_user', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_DELETE])]
    #[IsGranted(UserAccountVoter::ACCESS, subject: 'id', message: 'Access denied.')]
    public function delete(string $id, DeleteUserUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return new JsonResponse(['message' => 'User deleted successfully']);
    }
}
