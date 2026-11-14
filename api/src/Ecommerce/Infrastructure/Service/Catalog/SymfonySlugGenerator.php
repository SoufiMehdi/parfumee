<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Service\Catalog;

use App\Ecommerce\Domain\Service\Catalog\SlugGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class SymfonySlugGenerator implements SlugGeneratorInterface
{
    /**
     * The catalog is written in French: the locale is fixed so that a slug
     * does not depend on the locale of the request that created the product.
     */
    public const string LOCALE = 'fr';

    public function __construct(private SluggerInterface $slugger)
    {
    }

    public function generate(string $text): string
    {
        return $this->slugger->slug($text, '-', self::LOCALE)->lower()->toString();
    }
}
