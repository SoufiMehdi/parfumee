<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Domain\Model\Catalog;

use App\Ecommerce\Domain\Exception\Catalog\InvalidCategoryParentException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testACategoryWithoutParentIsTopLevel(): void
    {
        $category = new Category('cat-1', 'Bougies', 'bougies');

        self::assertTrue($category->isTopLevel());
        self::assertNull($category->getParent());
    }

    public function testACategoryIsFiledUnderATopLevelOne(): void
    {
        $parent = new Category('cat-1', 'Bougies', 'bougies');

        $child = new Category('cat-2', 'Bougies parfumées', 'bougies-parfumees', $parent);

        self::assertSame($parent, $child->getParent());
        self::assertFalse($child->isTopLevel());
    }

    public function testTwoLevelsOnly(): void
    {
        $child = new Category('cat-2', 'Bougies parfumées', 'bougies-parfumees', new Category('cat-1', 'Bougies', 'bougies'));

        $this->expectException(InvalidCategoryParentException::class);
        $this->expectExceptionMessage('The category "cat-2" is itself a child category: only a top-level category can hold others.');

        new Category('cat-3', 'Ambre', 'ambre', $child);
    }

    public function testACategoryCannotBeItsOwnParent(): void
    {
        $category = new Category('cat-1', 'Bougies', 'bougies');

        try {
            $category->moveUnder($category);
            self::fail('A category must not go under itself.');
        } catch (InvalidCategoryParentException $exception) {
            self::assertSame('A category cannot be its own parent.', $exception->getMessage());
        }

        self::assertTrue($category->isTopLevel());
    }

    public function testAChildCategoryGoesBackToTheTopLevel(): void
    {
        $child = new Category('cat-2', 'Fondants', 'fondants', new Category('cat-1', 'Bougies', 'bougies'));

        $child->moveUnder(null);

        self::assertTrue($child->isTopLevel());
    }

    public function testRenameChangesTheNameAndTheSlug(): void
    {
        $category = new Category('cat-1', 'Bougies', 'bougies');

        $category->rename('Bougies parfumées', 'bougies-parfumees');

        self::assertSame('Bougies parfumées', $category->getName());
        self::assertSame('bougies-parfumees', $category->getSlug());
    }
}
