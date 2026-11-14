<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\User;

use App\Ecommerce\Domain\ValueObject\User\PhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterUserRequestDto
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email,
        // The upper bound keeps the hashing cost bounded. The full password
        // policy (strength, compromised passwords) is P3-02.
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(min: 8, max: 4096)]
        public string $password,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $firstName,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $lastName,
        #[Assert\Regex(pattern: PhoneNumber::PATTERN, message: 'This value is not a valid phone number.')]
        public ?string $phoneNumber = null,
        #[Assert\Valid]
        public ?RegisterAddressDto $address = null,
    ) {
    }
}
