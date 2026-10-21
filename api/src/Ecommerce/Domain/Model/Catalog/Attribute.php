<?php

namespace App\Ecommerce\Domain\Model\Catalog;

class Attribute {
    private $weight;
    private $fragrance;
    private $size;
    private $description;
    private $presentation;

    public function __construct(
        ?float $weight = null,
        ?string $fragrance = null,
        ?string $size = null,
        ?string $description = null,
        ?string $presentation = null
    ) {
        $this->weight = $weight;
        $this->fragrance = $fragrance;
        $this->size = $size;
        $this->description = $description;
        $this->presentation = $presentation;
    }

    // Getters and Setters
    public function getWeight(): ?float { return $this->weight; }
    public function setWeight(?float $weight): void { $this->weight = $weight; }

    public function getFragrance(): ?string { return $this->fragrance; }
    public function setFragrance(?string $fragrance): void { $this->fragrance = $fragrance; }

    public function getSize(): ?string { return $this->size; }
    public function setSize(?string $size): void { $this->size = $size; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = $description; }

    public function getPresentation(): ?string { return $this->presentation; }
    public function setPresentation(?string $presentation): void { $this->presentation = $presentation; }
    public function toArray(): array {
        return [
            'weight' => $this->weight,
            'fragrance' => $this->fragrance,
            'size' => $this->size,
            'description' => $this->description,
            'presentation' => $this->presentation
        ];
    }
}