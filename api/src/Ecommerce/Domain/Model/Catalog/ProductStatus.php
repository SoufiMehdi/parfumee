<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Model\Catalog;

use App\Ecommerce\Domain\Exception\Catalog\InvalidProductStatusException;

/**
 * Where a product stands in the catalogue. Only a published product is shown by the shop.
 */
enum ProductStatus: string
{
    /** Being prepared: the back-office sees it, the shop does not. */
    case DRAFT = 'draft';
    /** On sale: the shop shows it. */
    case PUBLISHED = 'published';
    /** Taken out of the catalogue, but kept: what was sold must stay readable. */
    case ARCHIVED = 'archived';

    /**
     * What the back-office lists unless asked for a status: the archived products are set aside.
     *
     * @return non-empty-list<self>
     */
    public static function managed(): array
    {
        return [self::DRAFT, self::PUBLISHED];
    }

    /**
     * @throws InvalidProductStatusException for a value that is not a status (422, never a 500)
     */
    public static function fromValue(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidProductStatusException($value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
