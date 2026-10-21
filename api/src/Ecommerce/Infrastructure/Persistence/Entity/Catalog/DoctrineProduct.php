<?php

namespace App\Ecommerce\Infrastructure\Persistence\Entity\Catalog;

use Doctrine\ORM\Mapping as ORM;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
class DoctrineProduct
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $price;

    #[ORM\ManyToOne(targetEntity: DoctrineCategory::class)]
    #[ORM\JoinColumn(nullable: false)]
    private DoctrineCategory $category;


    /**
     * C'est ici que réside la magie pour le côté générique.
     * Le type 'json' sur PostgreSQL crée une colonne JSONB par défaut.
     */
    #[ORM\Column(type: 'json')]
    private array $attributes = [];
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: DoctrinePicture::class, cascade: ['persist', 'remove'])]
    private Collection $pictures;

    public function __construct(
        string $id, 
        string $name,
         float $price, DoctrineCategory $category, 
         array $attributes = []
         )
    {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->category = $category;
        $this->attributes = $attributes;
        $this->pictures = new ArrayCollection();
    }

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getPrice(): float { return $this->price; }
    public function setPrice(float $price): void { $this->price = $price;}
    public function getCategory(): DoctrineCategory { return $this->category; }
    public function setCategory(DoctrineCategory $category): void { $this->category = $category; }
    public function getAttributes(): array { return $this->attributes; }
    public function setAttributes(array $attributes): void { $this->attributes = $attributes;}
    public function getPictures(): Collection { return $this->pictures; }
    public function addPicture(DoctrinePicture $picture): void
    {
        if (!$this->pictures->contains($picture)) {
            $this->pictures[] = $picture;
            $picture->setProduct($this);    
        }
    }

}