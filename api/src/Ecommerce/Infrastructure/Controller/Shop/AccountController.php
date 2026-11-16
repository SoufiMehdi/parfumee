<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Shop;

use App\Ecommerce\Application\DTO\User\UpdateUserRequestDto;
use App\Ecommerce\Application\UseCase\User\DeleteUserUseCase;
use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Application\UseCase\User\UpdateUserUseCase;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
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
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The account of the signed-in customer. The account is always the one of the
 * token: the route carries no id, so there is no way to target someone else's.
 */
#[Route('/shop/me', name: 'shop_me_')]
#[OA\Tag(name: 'Shop · My account')]
#[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
class AccountController extends AbstractController
{
    #[Route('', name: 'get', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Read my account')]
    #[OA\Response(response: 200, description: 'The account of the token', content: new Model(type: UserView::class))]
    public function get(#[CurrentUser] DoctrineUser $user, GetUserUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        return $this->json($presenter->present($useCase->execute($user->getId())));
    }

    #[Route('', name: 'update', methods: [Request::METHOD_PUT])]
    #[OA\Put(summary: 'Update my account')]
    #[OA\Response(response: 200, description: 'The updated account', content: new Model(type: UserView::class))]
    #[OA\Response(response: 400, ref: '#/components/responses/BadRequest')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    public function update(
        #[CurrentUser] DoctrineUser $user,
        #[MapRequestPayload] UpdateUserRequestDto $dto,
        UpdateUserUseCase $useCase,
        UserPresenter $presenter,
    ): JsonResponse {
        return $this->json($presenter->present($useCase->execute($user->getId(), $dto)));
    }

    #[Route('', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OA\Delete(summary: 'Delete my account')]
    #[OA\Response(response: 200, description: 'The account is deleted', content: new Model(type: MessageView::class))]
    public function delete(#[CurrentUser] DoctrineUser $user, DeleteUserUseCase $useCase): JsonResponse
    {
        $useCase->execute($user->getId());

        return $this->json(new MessageView('User deleted successfully'));
    }
}
