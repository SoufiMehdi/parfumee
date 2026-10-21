<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\Mapper\User;

use App\Ecommerce\Application\DTO\User\UpdateUserRequestDTO;
use Symfony\Component\HttpFoundation\Request;

class UpdateUserRequestMapper
{
    public static function fromRequest(Request $request): UpdateUserRequestDTO
    {
        $data = $request->toArray();

        return new UpdateUserRequestDTO(
            firstName: $data['first_name'] ?? '',
            lastName: $data['last_name'] ?? '',
            phoneNumber: $data['phone_number'] ?? null,
        );
    }
}
