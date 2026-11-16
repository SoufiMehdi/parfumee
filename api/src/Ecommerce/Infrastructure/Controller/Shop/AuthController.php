<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Shop;

use App\Ecommerce\Application\DTO\User\LoginUserRequestDto;
use App\Ecommerce\Application\DTO\User\RegisterUserRequestDto;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use App\Ecommerce\Application\UseCase\User\RegisterUserUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public entry points of a customer: creating an account and signing in.
 */
#[Route('/api/shop', name: 'shop_')]
class AuthController extends AbstractController
{
    #[Route('/register', name: 'register', methods: [Request::METHOD_POST])]
    public function register(#[MapRequestPayload] RegisterUserRequestDto $dto, RegisterUserUseCase $useCase): JsonResponse
    {
        $useCase->execute($dto);

        return new JsonResponse(['message' => 'User created successfully'], JsonResponse::HTTP_CREATED);
    }

    #[Route('/login', name: 'login', methods: [Request::METHOD_POST])]
    public function login(#[MapRequestPayload] LoginUserRequestDto $dto, LoginUserUseCase $useCase): JsonResponse
    {
        return new JsonResponse($useCase->execute($dto));
    }
}
