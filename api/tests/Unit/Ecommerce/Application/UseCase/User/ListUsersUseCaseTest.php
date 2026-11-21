<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\DTO\User\ListUsersQueryDto;
use App\Ecommerce\Application\UseCase\User\ListUsersUseCase;
use App\Ecommerce\Domain\Exception\InvalidListQueryException;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\Repository\SortDirection;
use App\Ecommerce\Domain\Repository\User\UserCriteria;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\Repository\User\UserSortField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListUsersUseCaseTest extends TestCase
{
    public function testEveryParameterOfTheQueryReachesTheRepository(): void
    {
        $page = new Page([], 0, new Pagination(page: 2, perPage: 10));
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->once())->method('findPage')->with(
            new UserCriteria(search: 'ada', sortBy: UserSortField::LAST_NAME, direction: SortDirection::DESC),
            new Pagination(page: 2, perPage: 10),
        )->willReturn($page);

        $result = (new ListUsersUseCase($repository))->execute(
            new ListUsersQueryDto(q: 'ada', page: 2, perPage: 10, sort: 'last_name', order: 'desc'),
        );

        self::assertSame($page, $result);
    }

    public function testTheSearchIsTrimmed(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->once())->method('findPage')
            ->with(new UserCriteria(search: 'ada lovelace'), new Pagination())
            ->willReturn(new Page([], 0, new Pagination()));

        (new ListUsersUseCase($repository))->execute(new ListUsersQueryDto(q: '  ada lovelace '));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function noSearch(): iterable
    {
        yield 'no parameter' => [null];
        yield 'an empty search box' => [''];
        yield 'spaces only' => ['   '];
    }

    #[DataProvider('noSearch')]
    public function testAnEmptySearchListsEveryAccountByEmail(?string $q): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->once())->method('findPage')
            ->with(new UserCriteria(search: null, sortBy: UserSortField::EMAIL, direction: SortDirection::ASC), new Pagination())
            ->willReturn(new Page([], 0, new Pagination()));

        (new ListUsersUseCase($repository))->execute(new ListUsersQueryDto(q: $q));
    }

    public function testAnUnknownSortIsRefused(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->never())->method('findPage');

        $this->expectException(InvalidListQueryException::class);

        // Sorting by password hash would leak information through the order.
        (new ListUsersUseCase($repository))->execute(new ListUsersQueryDto(sort: 'password'));
    }
}
