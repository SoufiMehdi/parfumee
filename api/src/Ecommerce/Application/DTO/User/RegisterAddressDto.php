<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\User;

use App\Ecommerce\Domain\Model\User\Address;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The optional address given at registration. Lengths follow the columns of
 * the addresses table.
 */
final readonly class RegisterAddressDto
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $street,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 100)]
        public string $city,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 20)]
        public string $postalCode,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 100)]
        public string $country,
        #[Assert\Choice(choices: Address::TYPES)]
        public string $type = Address::TYPE_SHIPPING,
    ) {
    }
}
