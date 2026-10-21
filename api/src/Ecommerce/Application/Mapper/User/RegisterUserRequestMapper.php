<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\Mapper\User;

use App\Ecommerce\Application\DTO\User\RegisterUserRequestDTO;
use Symfony\Component\HttpFoundation\Request;

class RegisterUserRequestMapper
{
    public static function fromRequest(Request $request): RegisterUserRequestDTO
    {
        $data = $request->toArray(); // Nécessite que le body soit en JSON

        return new RegisterUserRequestDTO(
            email: $data['email'],
            password: $data['password'],
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            phoneNumber: $data['phone_number'] ?? null,
            address: $data['address'] ?? null
        );
    }
}