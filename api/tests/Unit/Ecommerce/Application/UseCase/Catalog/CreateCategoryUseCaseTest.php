<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\CreateCategoryDto;
use App\Ecommerce\Application\Service\Catalog\CategorySlugResolver;
use App\Ecommerce\Application\UseCase\Catalog\CreateCategoryUseCase;
use App\Ecommerce\Domain\Exception\Catalog\CategoryNotFoundException;
use App\Ecommerce\Domain\Exception\Catalog\InvalidCategoryParentException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Service\Catalog\SlugGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CreateCategoryUseCaseTest extends TestCase
{
    private const string PARENT_ID = '11111111-1111-4111-8111-111111111111';

    public function testATopLevelCategoryIsCreatedWithItsSlug(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects($this->never())->method('findById');
        $repository->expects($this->once())->method('save')->with($this->callback(
            static fn (Category $category): bool => 'Bougies' === $category->getName() && 'bougies' === $category->getSlug() && $category->isTopLevel(),
        ));

        $id = $this->useCase($repository)->execute(new CreateCategoryDto('Bougies'));

        self::assertTrue(Uuid::isValid($id));
    }

    public function testAChildCategoryIsFiledUnderItsParent(): void
    {
        $parent = new Category(self::PARENT_ID, 'Bougies', 'bougies');
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('findById')->with(self::PARENT_ID)->willReturn($parent);
        $repository->expects($this->once())->method('save')->with($this->callback(
            static fn (Category $category): bool => $category->getParent() === $parent,
        ));

        $this->useCase($repository)->execute(new CreateCategoryDto('Bougies parfumées', self::PARENT_ID));
    }

    public function testAnUnknownParentIsNotFound(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects($this->never())->method('save');

        $this->expectException(CategoryNotFoundException::class);

        $this->useCase($repository)->execute(new CreateCategoryDto('Bougies parfumées', self::PARENT_ID));
    }

    public function testAChildCategoryCannotHoldAnother(): void
    {
        $child = new Category(self::PARENT_ID, 'Bougies parfumées', 'bougies-parfumees', new Category('cat-1', 'Bougies', 'bougies'));
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturn($child);
        $repository->expects($this->never())->method('save');

        $this->expectException(InvalidCategoryParentException::class);

        $this->useCase($repository)->execute(new CreateCategoryDto('Ambre', self::PARENT_ID));
    }

    private function useCase(CategoryRepositoryInterface $repository): CreateCategoryUseCase
    {
        $slugGenerator = $this->createStub(SlugGeneratorInterface::class);
        $slugGenerator->method('generate')->willReturnCallback(static fn (string $name): string => strtolower(str_replace([' ', 'é'], ['-', 'e'], $name)));

        return new CreateCategoryUseCase($repository, new CategorySlugResolver($slugGenerator, $repository));
    }
}
