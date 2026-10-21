<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260515155631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pictures (id VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, alt VARCHAR(255) DEFAULT NULL, sort_order INT DEFAULT NULL, product_id UUID DEFAULT NULL, category_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8F7C2FC04584665A ON pictures (product_id)');
        $this->addSql('CREATE INDEX IDX_8F7C2FC012469DE2 ON pictures (category_id)');
        $this->addSql('ALTER TABLE pictures ADD CONSTRAINT FK_8F7C2FC04584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE pictures ADD CONSTRAINT FK_8F7C2FC012469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pictures DROP CONSTRAINT FK_8F7C2FC04584665A');
        $this->addSql('ALTER TABLE pictures DROP CONSTRAINT FK_8F7C2FC012469DE2');
        $this->addSql('DROP TABLE pictures');
    }
}
