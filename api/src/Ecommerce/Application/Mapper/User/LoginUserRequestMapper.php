<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\Mapper\User;

use App\Ecommerce\Application\DTO\User\LoginUserRequestDTO;
use Symfony\Component\HttpFoundation\Request;

class LoginUserRequestMapper
{
    public static function fromRequest(Request $request): LoginUserRequestDTO
    {
        $data = $request->toArray();

        return new LoginUserRequestDTO(
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
        );
    }
}
