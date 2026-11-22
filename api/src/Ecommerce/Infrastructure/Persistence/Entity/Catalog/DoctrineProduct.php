<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Persistence\Entity\Catalog;

use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Infrastructure\Persistence\Entity\Timestamps;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
class DoctrineProduct
{
    use Timestamps;

    // guid, not uuid: the uuid type would hand Doctrine a Uuid object while the property
    // holds a string, and every flush would then rewrite the row (and move updated_at).
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $slug;

    // A string, as PostgreSQL returns a DECIMAL ("24.90"): with a float, Doctrine would
    // always see the price as changed, and every flush would move updated_at. The model
    // still works with a float until prices move to cents (bug B15).
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price;

    #[ORM\ManyToOne(targetEntity: DoctrineCategory::class)]
    #[ORM\JoinColumn(nullable: false)]
    private DoctrineCategory $category;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ProductStatus::class)]
    private ProductStatus $status;

    /**
     * C'est ici que réside la magie pour le côté générique.
     * Le type 'json' sur PostgreSQL crée une colonne JSONB par défaut.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $attributes = [];

    /** @var Collection<int, DoctrinePicture> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: DoctrinePicture::class, cascade: ['persist', 'remove'])]
    private Collection $pictures;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        string $id,
        string $name,
        string $slug,
        float $price, DoctrineCategory $category,
        array $attributes = [],
        ProductStatus $status = ProductStatus::DRAFT,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->price = self::toDecimal($price);
        $this->category = $category;
        $this->attributes = $attributes;
        $this->status = $status;
        $this->pictures = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getPrice(): float
    {
        return (float) $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = self::toDecimal($price);
    }

    public function getStatus(): ProductStatus
    {
        return $this->status;
    }

    public function setStatus(ProductStatus $status): void
    {
        $this->status = $status;
    }

    public function getCategory(): DoctrineCategory
    {
        return $this->category;
    }

    public function setCategory(DoctrineCategory $category): void
    {
        $this->category = $category;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    /**
     * @return Collection<int, DoctrinePicture>
     */
    public function getPictures(): Collection
    {
        return $this->pictures;
    }

    public function addPicture(DoctrinePicture $picture): void
    {
        if (!$this->pictures->contains($picture)) {
            $this->pictures[] = $picture;
            $picture->setProduct($this);
        }
    }

    /**
     * The price as the DECIMAL(10, 2) column holds it, and as PostgreSQL returns it.
     */
    private static function toDecimal(float $price): string
    {
        return number_format($price, 2, '.', '');
    }
}
