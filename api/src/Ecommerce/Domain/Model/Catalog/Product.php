<?php

namespace App\Ecommerce\Domain\Model\Catalog;

use App\Ecommerce\Domain\Exception\Catalog\InvalidPriceException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Picture;
class Product
{
    private string $id;
    private string $name;
    private string $slug;
    private float $price;
    private Category $category;
    private ?Attribute $attributes; // Stockera tes données spécifiques (parfum, taille, etc.)

    /** @var Picture[] */
    private array $pictures = []; // Initialisation directe : plus jamais de erreur "non initialisé"
    public function __construct(
        string $id,
        string $name,
        string $slug,
        float $price,
        Category $category,
        ?Attribute $attributes = null,
        array $pictures = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->setPrice($price); // Utilisation d'un setter pour valider le métier
        $this->category = $category;
        $this->attributes = $attributes ?? new Attribute(); // Si aucun attribut n'est fourni, on initialise avec des valeurs par défaut
        $this->pictures = $pictures ?? []; // On peut aussi injecter les images à la création si besoin, sinon elles seront ajoutées via addPicture() plus tard
    }

    public function setPrice(float $price): void
    {
        if ($price < 0) {
            throw new InvalidPriceException($price);
        }
        $this->price = $price;
    }

    // Getters...
    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getAttributes(): ?Attribute { return $this->attributes; }
    public function getSlug(): string { return $this->slug; }
    public function getPrice(): float { return $this->price; }
    public function getCategory(): Category { return $this->category; }
    public function updateDetails(
        string $name, 
        float $price, 
        Category $category, 
        ?Attribute $attributes = null
    ): void 
    {
        $this->name = $name;
        $this->price = $price;
        $this->category = $category;
        $this->attributes = ($attributes) ? $attributes : $this->attributes;
        // Tu peux aussi recalculer le slug ici
        $this->slug = str_replace(' ', '-', strtolower($name));
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