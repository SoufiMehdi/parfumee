<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612051158 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE addresses (id UUID NOT NULL, type VARCHAR(50) NOT NULL, street VARCHAR(255) NOT NULL, city VARCHAR(100) NOT NULL, postal_code VARCHAR(20) NOT NULL, country VARCHAR(100) NOT NULL, is_default BOOLEAN NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6FCA7516A76ED395 ON addresses (user_id)');
        $this->addSql('ALTER TABLE addresses ADD CONSTRAINT FK_6FCA7516A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE users ADD first_name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE users ADD last_name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE users ADD phone_number VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE addresses DROP CONSTRAINT FK_6FCA7516A76ED395');
        $this->addSql('DROP TABLE addresses');
        $this->addSql('ALTER TABLE users DROP first_name');
        $this->addSql('ALTER TABLE users DROP last_name');
        $this->addSql('ALTER TABLE users DROP phone_number');
    }
}
