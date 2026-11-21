<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Common;

use App\Ecommerce\Domain\Repository\Page;

/**
 * Where a page sits in the whole list.
 */
final readonly class PaginationView
{
    /**
     * Header carrying the total too: the convention of React-admin and of most list clients.
     */
    public const string TOTAL_COUNT_HEADER = 'X-Total-Count';

    public function __construct(
        /** Number of the page, from 1. */
        public int $page,
        /** Maximum number of items on a page. */
        public int $perPage,
        /** Number of items matching the filters, over all pages. */
        public int $total,
        /** Number of pages, 0 when nothing matches. A page after the last one is empty, not an error. */
        public int $totalPages,
    ) {
    }

    /**
     * @template T
     *
     * @param Page<T> $page
     */
    public static function of(Page $page): self
    {
        return new self($page->pagination->page, $page->pagination->perPage, $page->total, $page->totalPages());
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [self::TOTAL_COUNT_HEADER => (string) $this->total];
    }
}
