<?php

namespace App\Ecommerce\Application\Mapper;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Domain\Model\Catalog\Attribute;

class AttributesMapper
{
    public function fromDto(CreateProductDto $dto): Attribute
    {
        return new Attribute(
            weight: (float) $dto->attributes['weight'] ?? null,
            fragrance: $dto->attributes['fragrance'] ?? null,
            size: $dto->attributes['size'] ?? null,
            description: $dto->attributes['description'] ?? null,
            presentation: $dto->attributes['presentation'] ?? null
        );
    }
    public function toDto(Attribute $attribute): array
    {
        return [
            'weight' => $attribute->getWeight(),
            'fragrance' => $attribute->getFragrance(),
            'size' => $attribute->getSize(),
            'description' => $attribute->getDescription(),
            'presentation' => $attribute->getPresentation()
        ];
    }
    public  function fromArray(array $attributes): Attribute
    {
        return new Attribute(
            weight: (float) $attributes['weight'] ?? null,
            fragrance: $attributes['fragrance'] ?? null,
            size: $attributes['size'] ?? null,
            description: $attributes['description'] ?? null,
            presentation: $attributes['presentation'] ?? null
        );
    }
}