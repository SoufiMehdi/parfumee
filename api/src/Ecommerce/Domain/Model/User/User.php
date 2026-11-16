<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Model\User;

use App\Ecommerce\Domain\Exception\User\UnknownRoleException;
use App\Ecommerce\Domain\ValueObject\User\Email;
use App\Ecommerce\Domain\ValueObject\User\PhoneNumber;

class User
{
    /** @var string[] */
    private array $roles;

    /** @var Address[] */
    private array $addresses;

    /**
     * @param string[]  $roles
     * @param Address[] $addresses
     */
    public function __construct(
        private readonly string $id,
        private Email $email,
        private string $passwordHash,
        private string $firstName,
        private string $lastName,
        private ?PhoneNumber $phoneNumber = null,
        array $roles = [Role::USER],
        array $addresses = [],
    ) {
        $this->roles = $roles;
        $this->addresses = $addresses;
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPhoneNumber(): ?PhoneNumber
    {
        return $this->phoneNumber;
    }

    /** @return Address[] */
    public function getAddresses(): array
    {
        return $this->addresses;
    }

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

    /**
     * Granting a role the account already holds changes nothing.
     */
    public function grantRole(string $role): void
    {
        if (!\in_array($role, Role::ALL, true)) {
            throw new UnknownRoleException($role);
        }

        if (!\in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
    }

    public function addAddress(Address $address): void
    {
        $this->addresses[] = $address;
    }
}
