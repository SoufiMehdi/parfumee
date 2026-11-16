<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Admin;

use App\Ecommerce\Application\DTO\User\UpdateUserRequestDto;
use App\Ecommerce\Application\UseCase\User\DeleteUserUseCase;
use App\Ecommerce\Application\UseCase\User\GetUsersUseCase;
use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Application\UseCase\User\UpdateUserUseCase;
use App\Ecommerce\Infrastructure\Presenter\Common\MessageView;
use App\Ecommerce\Infrastructure\Presenter\User\UserPresenter;
use App\Ecommerce\Infrastructure\Presenter\User\View\UserView;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Customer accounts as the back-office sees them: any account, not only one's own.
 * Requires ROLE_ADMIN: editors have no access to customer data.
 */
#[Route('/admin/users', name: 'admin_users_')]
#[OA\Tag(name: 'Back-office · Users')]
#[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
#[OA\Response(response: 403, ref: '#/components/responses/Forbidden')]
class UserController extends AbstractController
{
    #[Route('', name: 'list', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'List the accounts', description: 'Requires the administrator role.')]
    #[OA\Response(response: 200, description: 'Every account', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: UserView::class))))]
    public function list(GetUsersUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        return $this->json($presenter->presentCollection($useCase->execute()));
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Read an account', description: 'Requires the administrator role.')]
    #[OA\Response(response: 200, description: 'The account', content: new Model(type: UserView::class))]
    #[OA\Response(response: 404, ref: '#/components/responses/NotFound')]
    public function get(string $id, GetUserUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        return $this->json($presenter->present($useCase->execute($id)));
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_PUT])]
    #[OA\Put(summary: 'Update an account', description: 'Requires the administrator role.')]
    #[OA\Response(response: 200, description: 'The updated account', content: new Model(type: UserView::class))]
    #[OA\Response(response: 400, ref: '#/components/responses/BadRequest')]
    #[OA\Response(response: 404, ref: '#/components/responses/NotFound')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateUserRequestDto $dto,
        UpdateUserUseCase $useCase,
        UserPresenter $presenter,
    ): JsonResponse {
        return $this->json($presenter->present($useCase->execute($id, $dto)));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_DELETE])]
    #[OA\Delete(summary: 'Delete an account', description: 'Requires the administrator role.')]
    #[OA\Response(response: 200, description: 'The account is deleted', content: new Model(type: MessageView::class))]
    #[OA\Response(response: 404, ref: '#/components/responses/NotFound')]
    public function delete(string $id, DeleteUserUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return $this->json(new MessageView('User deleted successfully'));
    }
}
