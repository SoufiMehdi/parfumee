<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\User;

use App\Ecommerce\Domain\ValueObject\User\PhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateUserRequestDto
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $firstName,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $lastName,
        #[Assert\Regex(pattern: PhoneNumber::PATTERN, message: 'This value is not a valid phone number.')]
        public ?string $phoneNumber = null,
    ) {
    }
}
