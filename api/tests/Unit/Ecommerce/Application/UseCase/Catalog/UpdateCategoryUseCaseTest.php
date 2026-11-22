<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\UpdateCategoryDto;
use App\Ecommerce\Application\Service\Catalog\CategorySlugResolver;
use App\Ecommerce\Application\UseCase\Catalog\UpdateCategoryUseCase;
use App\Ecommerce\Domain\Exception\Catalog\CategoryNotFoundException;
use App\Ecommerce\Domain\Exception\Catalog\InvalidCategoryParentException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Service\Catalog\SlugGeneratorInterface;
use PHPUnit\Framework\TestCase;

final class UpdateCategoryUseCaseTest extends TestCase
{
    private const string ID = '22222222-2222-4222-8222-222222222222';
    private const string PARENT_ID = '11111111-1111-4111-8111-111111111111';

    public function testTheCategoryIsRenamedMovedAndReadAgainAfterTheSave(): void
    {
        $category = new Category(self::ID, 'Fondant', 'fondant');
        $parent = new Category(self::PARENT_ID, 'Bougies', 'bougies');
        $stored = new Category(self::ID, 'Fondants', 'fondants', $parent, new \DateTimeImmutable('2026-11-01T09:30:00+00:00'), new \DateTimeImmutable('2026-11-22T10:00:00+00:00'));
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturnCallback(static function (string $id) use ($category, $parent, $stored): Category {
            static $reads = 0;

            return self::PARENT_ID === $id ? $parent : (0 === $reads++ ? $category : $stored);
        });
        $repository->method('hasChildren')->willReturn(false);
        $repository->expects($this->once())->method('save')->with($this->callback(
            static fn (Category $saved): bool => 'Fondants' === $saved->getName() && 'fondants' === $saved->getSlug() && $saved->getParent() === $parent,
        ));

        $result = $this->useCase($repository)->execute(self::ID, new UpdateCategoryDto('Fondants', self::PARENT_ID));

        self::assertSame($stored, $result);
    }

    public function testTheSlugIsKeptWhenTheNameDoesNotChange(): void
    {
        // "bougies-2": the suffix it got at creation must survive a move.
        $category = new Category(self::ID, 'Bougies', 'bougies-2', new Category(self::PARENT_ID, 'Maison', 'maison'));
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturn($category);
        $slugGenerator = $this->createMock(SlugGeneratorInterface::class);
        $slugGenerator->expects($this->never())->method('generate');

        $result = (new UpdateCategoryUseCase($repository, new CategorySlugResolver($slugGenerator, $repository)))
            ->execute(self::ID, new UpdateCategoryDto('Bougies'));

        self::assertSame('bougies-2', $result->getSlug());
        // Without parent_id, the category goes back to the top level.
        self::assertTrue($result->isTopLevel());
    }

    public function testACategoryThatHoldsOthersStaysAtTheTopLevel(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturnCallback(static fn (string $id): Category => new Category($id, 'Name', 'name-'.$id));
        $repository->method('hasChildren')->with(self::ID)->willReturn(true);
        $repository->expects($this->never())->method('save');

        $this->expectException(InvalidCategoryParentException::class);
        $this->expectExceptionMessage('The category "22222222-2222-4222-8222-222222222222" holds child categories: it must stay at the top level.');

        $this->useCase($repository)->execute(self::ID, new UpdateCategoryDto('Bougies', self::PARENT_ID));
    }

    public function testACategoryCannotGoUnderItself(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturn(new Category(self::ID, 'Bougies', 'bougies'));
        $repository->method('hasChildren')->willReturn(false);
        $repository->expects($this->never())->method('save');

        $this->expectException(InvalidCategoryParentException::class);

        $this->useCase($repository)->execute(self::ID, new UpdateCategoryDto('Bougies', self::ID));
    }

    public function testAnUnknownCategoryIsNotFound(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects($this->never())->method('save');

        $this->expectException(CategoryNotFoundException::class);
        $this->expectExceptionMessage(self::ID);

        $this->useCase($repository)->execute(self::ID, new UpdateCategoryDto('Bougies'));
    }

    public function testAnUnknownParentIsNotFound(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturnCallback(static fn (string $id): ?Category => self::ID === $id ? new Category(self::ID, 'Bougies', 'bougies') : null);
        $repository->expects($this->never())->method('save');

        $this->expectException(CategoryNotFoundException::class);
        $this->expectExceptionMessage(self::PARENT_ID);

        $this->useCase($repository)->execute(self::ID, new UpdateCategoryDto('Bougies', self::PARENT_ID));
    }

    private function useCase(CategoryRepositoryInterface $repository): UpdateCategoryUseCase
    {
        $slugGenerator = $this->createStub(SlugGeneratorInterface::class);
        $slugGenerator->method('generate')->willReturnCallback(static fn (string $name): string => strtolower($name));

        return new UpdateCategoryUseCase($repository, new CategorySlugResolver($slugGenerator, $repository));
    }
}
