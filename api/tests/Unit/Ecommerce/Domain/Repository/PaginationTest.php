<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Domain\Repository;

use App\Ecommerce\Domain\Exception\InvalidListQueryException;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    public function testTheFirstPageStartsAtTheFirstItem(): void
    {
        $pagination = new Pagination();

        self::assertSame(1, $pagination->page);
        self::assertSame(Pagination::DEFAULT_PER_PAGE, $pagination->perPage);
        self::assertSame(0, $pagination->offset());
    }

    public function testThePagesFollowEachOther(): void
    {
        self::assertSame(20, (new Pagination(page: 2, perPage: 20))->offset());
        self::assertSame(250, (new Pagination(page: 11, perPage: 25))->offset());
    }

    public function testTheLargestPageSizeIsAccepted(): void
    {
        self::assertSame(Pagination::MAX_PER_PAGE, (new Pagination(perPage: Pagination::MAX_PER_PAGE))->perPage);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidPaginations(): iterable
    {
        yield 'page 0' => [0, 20];
        yield 'negative page' => [-1, 20];
        yield 'no item per page' => [1, 0];
        yield 'more items per page than allowed' => [1, Pagination::MAX_PER_PAGE + 1];
        yield 'a page after the last one allowed' => [Pagination::MAX_PAGE + 1, 20];
        yield 'the largest integer' => [\PHP_INT_MAX, 20];
    }

    #[DataProvider('invalidPaginations')]
    public function testAnInvalidPaginationIsRefused(int $page, int $perPage): void
    {
        $this->expectException(InvalidListQueryException::class);

        new Pagination($page, $perPage);
    }

    public function testTheFurthestPageIsAccepted(): void
    {
        $pagination = new Pagination(page: Pagination::MAX_PAGE, perPage: Pagination::MAX_PER_PAGE);

        self::assertSame((Pagination::MAX_PAGE - 1) * Pagination::MAX_PER_PAGE, $pagination->offset());
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function totals(): iterable
    {
        yield 'nothing' => [0, 20, 0];
        yield 'less than a page' => [1, 20, 1];
        yield 'exactly one page' => [20, 20, 1];
        yield 'one more than a page' => [21, 20, 2];
        yield 'many pages' => [101, 10, 11];
    }

    #[DataProvider('totals')]
    public function testThePageCountsThePagesOfTheWholeList(int $total, int $perPage, int $expectedPages): void
    {
        $page = new Page([], $total, new Pagination(perPage: $perPage));

        self::assertSame($expectedPages, $page->totalPages());
    }
}
