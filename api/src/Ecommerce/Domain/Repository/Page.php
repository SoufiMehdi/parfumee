<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Repository;

/**
 * One page of a list, and how many items the whole list holds.
 *
 * A page after the last one is not an error: it is empty, and its total is still right.
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param list<T> $items
     * @param int     $total number of items matching the criteria, over all pages
     */
    public function __construct(
        public array $items,
        public int $total,
        public Pagination $pagination,
    ) {
    }

    /**
     * Number of pages: 0 when nothing matches.
     */
    public function totalPages(): int
    {
        return intdiv($this->total + $this->pagination->perPage - 1, $this->pagination->perPage);
    }
}
