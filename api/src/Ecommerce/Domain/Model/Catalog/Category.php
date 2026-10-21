<?php

namespace App\Ecommerce\Domain\Model\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Picture;

class Category
{
    private string $id;
    private string $name;
    private string $slug;
    private ?array $pictures;

    public function __construct(string $id, string $name, string $slug)
    {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
    }

    // Getters
    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSlug(): string { return $this->slug; }
    public function addPicture(Picture $picture): void
    {
        $this->pictures[] = $picture;
    }
    public function getPictures(): ?array
    {
        return $this->pictures;
    }
}