<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Persistence\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * When a row was created and last changed. Every entity uses it.
 *
 * Gedmo Timestampable fills both dates when the row is inserted, and moves
 * updated_at forward whenever Doctrine writes a change to the row. The
 * application never sets them: there is no setter on purpose.
 *
 * The columns keep the time zone (timestamptz): a date stays the same instant
 * whatever the time zone of PHP or of the database session.
 */
trait Timestamps
{
    // Null only between the creation of the object and its first flush.
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
