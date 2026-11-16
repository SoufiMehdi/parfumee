<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Common;

/**
 * A plain confirmation.
 */
final readonly class MessageView
{
    public function __construct(
        public string $message,
    ) {
    }
}
