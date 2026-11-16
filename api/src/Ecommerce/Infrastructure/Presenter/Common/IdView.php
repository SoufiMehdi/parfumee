<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Common;

/**
 * The id of the resource that was just created.
 */
final readonly class IdView
{
    public function __construct(
        public string $id,
    ) {
    }
}
