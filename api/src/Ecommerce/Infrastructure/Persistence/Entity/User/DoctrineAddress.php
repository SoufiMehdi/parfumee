<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Persistence\Entity\User;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'addresses')]
class DoctrineAddress
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: DoctrineUser::class, inversedBy: 'addresses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private DoctrineUser $user;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type; // 'shipping', 'billing'

    #[ORM\Column(type: 'string', length: 255)]
    private string $street;

    #[ORM\Column(type: 'string', length: 100)]
    private string $city;

    #[ORM\Column(type: 'string', length: 20)]
    private string $postalCode;

    #[ORM\Column(type: 'string', length: 100)]
    private string $country;

    #[ORM\Column(type: 'boolean')]
    private bool $isDefault = false;

    public function __construct(
        string $id,
        DoctrineUser $user,
        string $type,
        string $street,
        string $city,
        string $postalCode,
        string $country,
        bool $isDefault = false
    ) {
        $this->id = $id;
        $this->user = $user;
        $this->type = $type;
        $this->street = $street;
        $this->city = $city;
        $this->postalCode = $postalCode;
        $this->country = $country;
        $this->isDefault = $isDefault;
    }

    public function getId(): string { return $this->id; }
    public function getUser(): DoctrineUser { return $this->user; }
    public function getType(): string { return $this->type; }
    public function getStreet(): string { return $this->street; }
    public function setStreet(string $value): void { $this->street = $value; }
    public function getCity(): string { return $this->city; }
    public function setCity(string $value): void { $this->city = $value; }
    public function getPostalCode(): string { return $this->postalCode; }
    public function setPostalCode(string $value): void { $this->postalCode = $value; }
    public function getCountry(): string { return $this->country; }
    public function setCountry(string $value): void { $this->country = $value; }
    public function isDefault(): bool { return $this->isDefault; }
    public function setIsDefault(bool $value): void { $this->isDefault = $value; }
    public function setType(string $value): void { $this->type = $value; }
}