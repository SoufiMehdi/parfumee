<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds created_at and updated_at to every table of the application [P1-08].
 *
 * Nobody knows when the existing rows were really created: they get the time
 * of the migration. The default that fills them is dropped right after, so that
 * only the application sets these dates (Gedmo Timestampable, see the Timestamps
 * trait). refresh_tokens belongs to the refresh token bundle and is left alone.
 */
final class Version20261121195209 extends AbstractMigration
{
    private const array TABLES = ['addresses', 'categories', 'pictures', 'products', 'users'];

    public function getDescription(): string
    {
        return 'Add created_at and updated_at to every table; existing rows get the time of the migration';
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql(\sprintf(
                'ALTER TABLE %s ADD created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, ADD updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL',
                $table,
            ));
            $this->addSql(\sprintf('ALTER TABLE %s ALTER created_at DROP DEFAULT, ALTER updated_at DROP DEFAULT', $table));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql(\sprintf('ALTER TABLE %s DROP created_at, DROP updated_at', $table));
        }
    }
}
