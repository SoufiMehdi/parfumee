<?php

namespace App\Ecommerce\Infrastructure\Persistence\Entity\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Picture;
use Doctrine\ORM\Mapping as ORM;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;

#[ORM\Entity]
#[ORM\Table(name: 'pictures')]
class DoctrinePicture
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    private string $id;

    #[ORM\Column(type: 'string')]
    private string $url;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $alt;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $sortOrder;

    #[ORM\ManyToOne(targetEntity: DoctrineProduct::class, inversedBy: 'pictures', cascade: ['persist'])]
    private ?DoctrineProduct $product = null;

    #[ORM\ManyToOne(targetEntity: DoctrineCategory::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?DoctrineCategory $category = null;

    public function __construct(
        string $id,
        string $url,
        ?string $alt = null,
        ?int $sortOrder = null,
        ?DoctrineProduct $product = null,
        ?DoctrineCategory $category = null
    ) {
        $this->id = $id;
        $this->url = $url;
        $this->alt = $alt;
        $this->sortOrder = $sortOrder;
        $this->product = $product;
        $this->category = $category;
    }
    public function getId(): string
    {
        return $this->id;
    }
    public function getUrl(): string
    {
        return $this->url;
    }
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }
    public function getAlt(): ?string
    {
        return $this->alt;
    }
    public function setAlt(?string $alt): void
    {
        $this->alt = $alt;
    }
    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }
    public function setSortOrder(?int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }
    public function getProduct(): ?DoctrineProduct
    {
        return $this->product;
    }
    public function setProduct(?DoctrineProduct $product): void
    {
        $this->product = $product;
    }
    public function getCategory(): ?DoctrineCategory
    {
        return $this->category;
    }
    public function setCategory(?DoctrineCategory $category): void
    {
        $this->category = $category;
    }
}