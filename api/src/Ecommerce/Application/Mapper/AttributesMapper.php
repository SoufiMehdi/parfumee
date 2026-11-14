<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\Mapper;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Domain\Model\Catalog\Attribute;

class AttributesMapper
{
    public function fromDto(CreateProductDto $dto): Attribute
    {
        return $this->fromArray($dto->attributes);
    }

    /**
     * @return array{weight: ?float, fragrance: ?string, size: ?string, description: ?string, presentation: ?string}
     */
    public function toDto(Attribute $attribute): array
    {
        return [
            'weight' => $attribute->getWeight(),
            'fragrance' => $attribute->getFragrance(),
            'size' => $attribute->getSize(),
            'description' => $attribute->getDescription(),
            'presentation' => $attribute->getPresentation(),
        ];
    }

    /**
     * Every attribute is optional: a missing one is null, never 0 or ''.
     *
     * @param array<string, mixed> $attributes
     */
    public function fromArray(array $attributes): Attribute
    {
        return new Attribute(
            weight: isset($attributes['weight']) ? (float) $attributes['weight'] : null,
            fragrance: $attributes['fragrance'] ?? null,
            size: $attributes['size'] ?? null,
            description: $attributes['description'] ?? null,
            presentation: $attributes['presentation'] ?? null,
        );
    }
}
