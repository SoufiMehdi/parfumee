<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Service\Catalog;

interface SlugGeneratorInterface
{
    /**
     * Turns a free text into a URL-safe slug: lowercase ASCII letters, digits and dashes.
     * Returns an empty string when the text holds nothing usable.
     */
    public function generate(string $text): string;
}
