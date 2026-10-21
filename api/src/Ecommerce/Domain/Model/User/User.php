<?php

namespace App\Ecommerce\Domain\Model\User;

use App\Ecommerce\Domain\ValueObject\User\Email;
use App\Ecommerce\Domain\ValueObject\User\PhoneNumber;
use Symfony\Component\Uid\Uuid;

class User
{
    private array $roles;

    /** @var Address[] */
    private array $addresses;

    public function __construct(
        private readonly string $id,
        private Email $email,
        private string $passwordHash,
        private string $firstName,
        private string $lastName,
        private ?PhoneNumber $phoneNumber = null,
        array $roles = ['ROLE_USER'],
        array $addresses = []
    ) {
        $this->roles = $roles;
        $this->addresses = $addresses;
    }

    // Getters
    public function getId(): string { return $this->id; }
    public function getEmail(): Email { return $this->email; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function getFirstName(): string { return $this->firstName; }
    public function getLastName(): string { return $this->lastName; }
    public function getRoles(): array { return $this->roles; }
    public function getPhoneNumber(): ?PhoneNumber { return $this->phoneNumber; }
    /** @return Address[] */
    public function getAddresses(): array { return $this->addresses; }
    // Logique métier
    public function updateProfile(string $firstName, string $lastName): void
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
    }

    public function changePassword(string $newPasswordHash): void
    {
        // Ici, on pourrait ajouter des règles métier sur la complexité si besoin
        $this->passwordHash = $newPasswordHash;
    }


    public function updatePhoneNumber(PhoneNumber $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function addAddress(Address $address): void
    {
        $this->addresses[] = $address;
    }
}