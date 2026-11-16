<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Shop;

use App\Ecommerce\Application\DTO\User\LoginUserRequestDto;
use App\Ecommerce\Application\DTO\User\RegisterUserRequestDto;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use App\Ecommerce\Application\UseCase\User\RegisterUserUseCase;
use App\Ecommerce\Infrastructure\Presenter\Common\MessageView;
use App\Ecommerce\Infrastructure\Presenter\User\View\SessionView;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public entry points of a customer: creating an account and signing in.
 */
#[Route('/shop', name: 'shop_')]
#[OA\Tag(name: 'Shop · Authentication')]
#[Security(name: null)]
class AuthController extends AbstractController
{
    #[Route('/register', name: 'register', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Create a customer account')]
    #[OA\Response(response: 201, description: 'The account is created', content: new Model(type: MessageView::class))]
    #[OA\Response(response: 400, ref: '#/components/responses/BadRequest')]
    #[OA\Response(response: 409, description: 'The email is already taken', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    public function register(#[MapRequestPayload] RegisterUserRequestDto $dto, RegisterUserUseCase $useCase): JsonResponse
    {
        $useCase->execute($dto);

        return $this->json(new MessageView('User created successfully'), JsonResponse::HTTP_CREATED);
    }

    #[Route('/login', name: 'login', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Sign in')]
    #[OA\Response(response: 200, description: 'The session', content: new Model(type: SessionView::class))]
    #[OA\Response(response: 400, ref: '#/components/responses/BadRequest')]
    #[OA\Response(response: 401, description: 'Wrong email or password', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    public function login(#[MapRequestPayload] LoginUserRequestDto $dto, LoginUserUseCase $useCase): JsonResponse
    {
        $session = $useCase->execute($dto);

        return $this->json(new SessionView($session['token'], $session['refresh_token']));
    }
}
