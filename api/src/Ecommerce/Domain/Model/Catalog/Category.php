<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Model\Catalog;

use App\Ecommerce\Domain\Exception\Catalog\InvalidCategoryParentException;

/**
 * A category of the catalogue. Categories have two levels: a top-level category may hold
 * child categories, and a child category holds none. A shop menu needs no more, and no
 * category can then end up inside itself.
 */
class Category
{
    private string $id;
    private string $name;
    private string $slug;
    private ?Category $parent = null;
    /** @var Picture[] */
    private array $pictures = [];

    /**
     * @param Category|null           $parent    a top-level category, or null for a top-level one
     * @param \DateTimeImmutable|null $createdAt set when the category is first saved: null for a category never saved
     * @param \DateTimeImmutable|null $updatedAt set when a change of the category is saved
     */
    public function __construct(
        string $id,
        string $name,
        string $slug,
        ?Category $parent = null,
        private readonly ?\DateTimeImmutable $createdAt = null,
        private readonly ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->moveUnder($parent);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function rename(string $name, string $slug): void
    {
        $this->name = $name;
        $this->slug = $slug;
    }

    public function getParent(): ?Category
    {
        return $this->parent;
    }

    public function isTopLevel(): bool
    {
        return null === $this->parent;
    }

    /**
     * Makes the category a child of $parent, or a top-level category with null.
     *
     * Whether the category has children of its own is not known here: the use case checks
     * it before (a category with children must stay at the top level).
     *
     * @throws InvalidCategoryParentException when $parent is this category, or a child category
     */
    public function moveUnder(?Category $parent): void
    {
        if (null !== $parent) {
            if ($parent->getId() === $this->id) {
                throw InvalidCategoryParentException::itself();
            }
            if (!$parent->isTopLevel()) {
                throw InvalidCategoryParentException::notTopLevel($parent->getId());
            }
        }

        $this->parent = $parent;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function addPicture(Picture $picture): void
    {
        $this->pictures[] = $picture;
    }

    /**
     * @return Picture[]
     */
    public function getPictures(): array
    {
        return $this->pictures;
    }
}
