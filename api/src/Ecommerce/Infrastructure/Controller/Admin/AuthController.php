<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Admin;

use App\Ecommerce\Application\DTO\User\LoginUserRequestDto;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sign-in of the back-office. It issues the same token as the shop sign-in:
 * what an account may do is decided on every admin route, from its roles.
 */
#[Route('/api/admin', name: 'admin_')]
class AuthController extends AbstractController
{
    #[Route('/login', name: 'login', methods: [Request::METHOD_POST])]
    public function login(#[MapRequestPayload] LoginUserRequestDto $dto, LoginUserUseCase $useCase): JsonResponse
    {
        return new JsonResponse($useCase->execute($dto));
    }
}
