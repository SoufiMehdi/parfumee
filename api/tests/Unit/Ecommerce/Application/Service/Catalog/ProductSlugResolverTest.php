<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\Service\Catalog;

use App\Ecommerce\Application\Service\Catalog\ProductSlugResolver;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Service\Catalog\SlugGeneratorInterface;
use PHPUnit\Framework\TestCase;

final class ProductSlugResolverTest extends TestCase
{
    public function testAFreeSlugIsUsedAsIs(): void
    {
        self::assertSame('amber-candle', $this->resolver('amber-candle', taken: [])->resolve('Amber candle'));
    }

    public function testATakenSlugGetsTheFirstFreeSuffix(): void
    {
        $resolver = $this->resolver('amber-candle', taken: ['amber-candle', 'amber-candle-2']);

        self::assertSame('amber-candle-3', $resolver->resolve('Amber candle'));
    }

    public function testTheProductBeingRenamedIsPassedToTheRepository(): void
    {
        $generator = self::createStub(SlugGeneratorInterface::class);
        $generator->method('generate')->willReturn('amber-candle');
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->once())->method('slugExists')->with('amber-candle', 'product-id')->willReturn(false);

        self::assertSame('amber-candle', (new ProductSlugResolver($generator, $repository))->resolve('Amber candle', 'product-id'));
    }

    public function testANameWithNothingUsableFallsBackToAGenericSlug(): void
    {
        self::assertSame('product', $this->resolver('', taken: [])->resolve('!!!'));
        self::assertSame('product-2', $this->resolver('', taken: ['product'])->resolve('!!!'));
    }

    public function testALongSlugIsCutSoThatTheSuffixFitsInTheColumn(): void
    {
        $long = str_repeat('a', 239).'-'.str_repeat('b', 15);

        $slug = $this->resolver($long, taken: [str_repeat('a', 239)])->resolve('whatever');

        // Cut at 240 characters, the trailing dash removed, then suffixed.
        self::assertSame(str_repeat('a', 239).'-2', $slug);
    }

    /**
     * @param list<string> $taken
     */
    private function resolver(string $generated, array $taken): ProductSlugResolver
    {
        $generator = self::createStub(SlugGeneratorInterface::class);
        $generator->method('generate')->willReturn($generated);
        $repository = self::createStub(ProductRepositoryInterface::class);
        $repository->method('slugExists')->willReturnCallback(
            static fn (string $slug): bool => \in_array($slug, $taken, true),
        );

        return new ProductSlugResolver($generator, $repository);
    }
}
