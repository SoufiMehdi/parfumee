<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Persistence\Entity\Catalog;

use App\Ecommerce\Infrastructure\Persistence\Entity\Timestamps;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'categories')]
class DoctrineCategory
{
    use Timestamps;

    // guid, not uuid: the uuid type would hand Doctrine a Uuid object while the property
    // holds a string, and every flush would then rewrite the row (and move updated_at).
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $slug;

    // No cascade: a category that holds others cannot be deleted (DeleteCategoryUseCase).
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?DoctrineCategory $parent;

    public function __construct(string $id, string $name, string $slug, ?DoctrineCategory $parent = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->parent = $parent;
    }

    // Getters / Setters...
    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getParent(): ?DoctrineCategory
    {
        return $this->parent;
    }

    public function setParent(?DoctrineCategory $parent): void
    {
        $this->parent = $parent;
    }
}
