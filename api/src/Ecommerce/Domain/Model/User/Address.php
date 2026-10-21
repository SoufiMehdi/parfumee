<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Model\User;

class Address
{
    public function __construct(
        private readonly string $id,
        private string $type, // shipping | billing
        private string $street,
        private string $city,
        private string $postalCode,
        private string $country,
        private bool $isDefault = false,
    ) {}

    public function getId(): string { return $this->id; }
    public function getType(): string { return $this->type; }
    public function getStreet(): string { return $this->street; }
    public function getCity(): string { return $this->city; }
    public function getPostalCode(): string { return $this->postalCode; }
    public function getCountry(): string { return $this->country; }
    public function isDefault(): bool { return $this->isDefault; }

    public function update(string $street, string $city, string $postalCode, string $country): void
    {
        $this->street = $street;
        $this->city = $city;
        $this->postalCode = $postalCode;
        $this->country = $country;
    }

    public function setAsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }
}