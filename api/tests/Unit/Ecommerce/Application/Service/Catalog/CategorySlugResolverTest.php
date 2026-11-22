<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\Service\Catalog;

use App\Ecommerce\Application\Service\Catalog\CategorySlugResolver;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Service\Catalog\SlugGeneratorInterface;
use PHPUnit\Framework\TestCase;

final class CategorySlugResolverTest extends TestCase
{
    public function testATakenSlugGetsTheFirstFreeSuffix(): void
    {
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('slugExists')->willReturnCallback(static fn (string $slug): bool => \in_array($slug, ['bougies', 'bougies-2'], true));

        self::assertSame('bougies-3', $this->resolver('bougies', $repository)->resolve('Bougies'));
    }

    public function testTheRenamedCategoryDoesNotCollideWithItself(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects($this->once())->method('slugExists')->with('bougies', 'cat-1')->willReturn(false);

        self::assertSame('bougies', $this->resolver('bougies', $repository)->resolve('Bougies', 'cat-1'));
    }

    public function testANameWithoutLettersFallsBackToAGenericSlug(): void
    {
        self::assertSame('category', $this->resolver('', $this->createStub(CategoryRepositoryInterface::class))->resolve('!!!'));
    }

    private function resolver(string $generated, CategoryRepositoryInterface $repository): CategorySlugResolver
    {
        $generator = $this->createStub(SlugGeneratorInterface::class);
        $generator->method('generate')->willReturn($generated);

        return new CategorySlugResolver($generator, $repository);
    }
}
