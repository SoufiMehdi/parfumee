<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Gives every product a status: draft, published or archived [P2-02].
 *
 * The existing products were all shown by the shop: they are published, so that the shop
 * keeps showing them. The default that fills them is dropped right after: a new product
 * gets its status from the application (a draft, unless told otherwise). The check keeps
 * any other value out of the column.
 */
final class Version20261122162959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the status of the products; the existing products are published';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE products ADD status VARCHAR(20) DEFAULT 'published' NOT NULL");
        $this->addSql('ALTER TABLE products ALTER status DROP DEFAULT');
        $this->addSql("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('draft', 'published', 'archived'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP CONSTRAINT products_status_check');
        $this->addSql('ALTER TABLE products DROP status');
    }
}
