<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Model\Catalog;

class Category
{
    private string $id;
    private string $name;
    private string $slug;
    /** @var Picture[] */
    private array $pictures = [];

    public function __construct(string $id, string $name, string $slug)
    {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function addPicture(Picture $picture): void
    {
        $this->pictures[] = $picture;
    }

    /**
     * @return Picture[]
     */
    public function getPictures(): array
    {
        return $this->pictures;
    }
}
