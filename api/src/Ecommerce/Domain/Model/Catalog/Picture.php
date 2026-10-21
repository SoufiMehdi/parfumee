<?php

namespace App\Ecommerce\Domain\Model\Catalog;

readonly class Picture
{

    public function __construct(
        private string $id,
        private string $url,
        private ?string $alt = null,
        private ?int $sortOrder = null,
        private ?Product $product = null,
        private ?Category $category = null
    ){
    }

    public function getId(): string
    {
        return $this->id;
    }
    public function url(): string
    {
        return $this->url;
    }
    public function getAlt(): ?string
    {
        return $this->alt;
    }
    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }
    public function getProduct(): ?Product
    {
        return $this->product;
    }
    public function getCategory(): ?Category
    {
        return $this->category;
    }
}   