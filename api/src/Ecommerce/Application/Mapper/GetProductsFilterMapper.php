<?php

namespace App\Ecommerce\Application\Mapper;

use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;

class GetProductsFilterMapper
{
    public function map (array $data): GetProductsFilterDto
    {
        return new GetProductsFilterDto(
            categoryId: $data['category_id'] ?? null,
            minPrice: isset($data['min_price']) ? (float)$data['min_price'] : null,
            maxPrice: isset($data['max_price']) ? (float)$data['max_price'] : null,
            scent: $data['scent'] ?? null,
            size: $data['size'] ?? null
        );
    }
}
