<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Shop;

use App\Ecommerce\Application\DTO\User\LoginUserRequestDto;
use App\Ecommerce\Application\DTO\User\RegisterUserRequestDto;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use App\Ecommerce\Application\UseCase\User\RegisterUserUseCase;
use App\Ecommerce\Infrastructure\Presenter\Common\MessageView;
use App\Ecommerce\Infrastructure\Presenter\User\View\SessionView;
use App\Ecommerce\Infrastructure\Security\AuthenticationThrottle;
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
    #[OA\Post(summary: 'Create a customer account', description: 'The number of attempts from one address is limited, whatever their outcome.')]
    #[OA\Response(response: 201, description: 'The account is created', content: new Model(type: MessageView::class))]
    #[OA\Response(response: 400, ref: '#/components/responses/BadRequest')]
    #[OA\Response(response: 409, description: 'The email is already taken', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[OA\Response(response: 429, ref: '#/components/responses/TooManyRequests')]
    public function register(
        Request $request,
        #[MapRequestPayload] RegisterUserRequestDto $dto,
        RegisterUserUseCase $useCase,
        AuthenticationThrottle $throttle,
    ): JsonResponse {
        $throttle->consumeRegistration($request);
        $useCase->execute($dto);

        return $this->json(new MessageView('User created successfully'), JsonResponse::HTTP_CREATED);
    }

    #[Route('/login', name: 'login', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Sign in', description: 'Failed attempts are limited per email and per address; a successful sign-in clears the count of its email.')]
    #[OA\Response(response: 200, description: 'The session', content: new Model(type: SessionView::class))]
    #[OA\Response(response: 400, ref: '#/components/responses/BadRequest')]
    #[OA\Response(response: 401, description: 'Wrong email or password', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    #[OA\Response(response: 429, ref: '#/components/responses/TooManyRequests')]
    public function login(
        Request $request,
        #[MapRequestPayload] LoginUserRequestDto $dto,
        LoginUserUseCase $useCase,
        AuthenticationThrottle $throttle,
    ): JsonResponse {
        // Counted before the password is checked: a wrong one leaves the attempt counted.
        $throttle->consumeSignIn($request, $dto->email);
        $session = $useCase->execute($dto);
        $throttle->resetSignIn($request, $dto->email);

        return $this->json(new SessionView($session['token'], $session['refresh_token']));
    }
}
