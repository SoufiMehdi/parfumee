<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Repository;

use App\Ecommerce\Domain\Exception\InvalidListQueryException;

/**
 * Which slice of a list is asked for. Every list the API returns is paginated.
 */
final readonly class Pagination
{
    public const int DEFAULT_PER_PAGE = 20;
    public const int MAX_PER_PAGE = 100;
    /** 100 million items at 100 per page: far beyond any list of the shop, and the offset stays small. */
    public const int MAX_PAGE = 1_000_000;

    public function __construct(
        /** Number of the page, from 1. */
        public int $page = 1,
        /** Maximum number of items on a page. */
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {
        if ($page < 1 || $page > self::MAX_PAGE) {
            throw new InvalidListQueryException(\sprintf('The page must be between 1 and %d.', self::MAX_PAGE));
        }

        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new InvalidListQueryException(\sprintf('The number of items per page must be between 1 and %d.', self::MAX_PER_PAGE));
        }
    }

    /**
     * Number of items before the first one of the page.
     */
    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
