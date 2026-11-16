<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\User;

use App\Ecommerce\Domain\Model\User\Role;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateBackOfficeUserDto
{
    /** Longer than a customer password: this account can change the catalogue and read customer data. */
    public const int PASSWORD_MIN_LENGTH = 12;

    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(min: self::PASSWORD_MIN_LENGTH, max: 4096)]
        public string $password,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $firstName,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $lastName,
        #[Assert\Choice(choices: Role::BACK_OFFICE)]
        public string $role = Role::ADMIN,
    ) {
    }
}
