<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\Mapper\User;

use App\Ecommerce\Application\DTO\User\RegisterUserRequestDTO;
use App\Ecommerce\Application\Mapper\User\RegisterUserRequestMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RegisterUserRequestMapperTest extends TestCase
{
    public function testFromRequestSuccessfullyMapsData(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $request = new Request(
            content: json_encode($data),
            server: ['HTTP_CONTENT_TYPE' => 'application/json']
        );

        $dto = RegisterUserRequestMapper::fromRequest($request);

        $this->assertInstanceOf(RegisterUserRequestDTO::class, $dto);
        $this->assertEquals('test@example.com', $dto->email);
        $this->assertEquals('password123', $dto->password);
        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('Doe', $dto->lastName);
    }
}
