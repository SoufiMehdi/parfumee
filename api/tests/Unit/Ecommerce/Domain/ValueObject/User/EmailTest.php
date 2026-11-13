<?php

namespace App\Tests\Unit\Ecommerce\Domain\ValueObject\User;

use App\Ecommerce\Domain\ValueObject\User\Email;
use PHPUnit\Framework\TestCase;
use App\Ecommerce\Domain\Exception\User\InvalidEmailException;
use PHPUnit\Framework\Attributes\DataProvider;
class EmailTest extends TestCase
{
    public function testItShouldCreateAValidEmail(): void
    {
        $emailString = 'contact@exemple.com';
        $email = new Email($emailString);

        $this->assertEquals($emailString, $email->getValue());
    }

    #[DataProvider('invalidEmailProvider')]
    public function testItShouldThrowExceptionForInvalidEmail(string $invalidEmail): void
    {
        $this->expectException(InvalidEmailException::class);
        $this->expectExceptionMessage("Invalid email format: $invalidEmail");

        new Email($invalidEmail);
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'missing @' => ['invalidemail.com'],
            'missing domain' => ['user@'],
            'empty string' => [''],
            'spaces' => ['user @exemple.com'],
        ];
    }
}