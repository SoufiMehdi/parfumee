<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Adds the unique slug of products [P0-06].
 *
 * Existing products get a slug computed from their name, with the same rule
 * as the application (SymfonySlugGenerator + ProductSlugResolver).
 */
final class Version20261114170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a unique slug column to products and backfill it from the product names';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD slug VARCHAR(255) DEFAULT NULL');

        $slugger = new AsciiSlugger('fr');
        $used = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, name FROM products ORDER BY id') as $row) {
            $base = rtrim(substr($slugger->slug((string) $row['name'])->lower()->toString(), 0, 240), '-');
            if ('' === $base) {
                $base = 'product';
            }

            $slug = $base;
            for ($suffix = 2; isset($used[$slug]); ++$suffix) {
                $slug = $base.'-'.$suffix;
            }
            $used[$slug] = true;

            $this->addSql('UPDATE products SET slug = :slug WHERE id = :id', ['slug' => $slug, 'id' => $row['id']]);
        }

        $this->addSql('ALTER TABLE products ALTER slug SET NOT NULL');
        $this->addSql('ALTER TABLE products ALTER slug DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B3BA5A5A989D9B62 ON products (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_B3BA5A5A989D9B62');
        $this->addSql('ALTER TABLE products DROP slug');
    }
}
