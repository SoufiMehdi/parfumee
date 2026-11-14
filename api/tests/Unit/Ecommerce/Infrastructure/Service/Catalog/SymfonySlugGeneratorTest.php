<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Infrastructure\Service\Catalog;

use App\Ecommerce\Infrastructure\Service\Catalog\SymfonySlugGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class SymfonySlugGeneratorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'accents and apostrophe (the example of bug B4)' => ["Bougie Été à l'Ambre", 'bougie-ete-a-l-ambre'];
        yield 'uppercase' => ['BOUGIE Vanille', 'bougie-vanille'];
        yield 'punctuation and repeated spaces' => ['  Fondant :  fleur de coton !  ', 'fondant-fleur-de-coton'];
        yield 'ampersand is dropped, not translated to English' => ['Ambre & Vanille', 'ambre-vanille'];
        yield 'ligature and cedilla' => ['Cœur de façade', 'coeur-de-facade'];
        yield 'digits are kept' => ['Coffret 3 bougies 180g', 'coffret-3-bougies-180g'];
        yield 'nothing usable' => ['!!!', ''];
    }

    #[DataProvider('names')]
    public function testGenerate(string $name, string $expected): void
    {
        // The default locale is deliberately not French: the slug must not depend on it.
        $generator = new SymfonySlugGenerator(new AsciiSlugger('en'));

        self::assertSame($expected, $generator->generate($name));
    }
}
