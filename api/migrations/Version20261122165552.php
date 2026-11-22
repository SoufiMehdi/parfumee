<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lets a category be filed under a top-level one [P2-01].
 *
 * The existing categories stay top-level (parent_id null). The foreign key has no cascade:
 * a category that holds others is never deleted. The check keeps a category from being its
 * own parent.
 *
 * The two-level rule is checked by the application (Category::moveUnder(), then
 * UpdateCategoryUseCase), but two requests at the same time could both pass it (X under Y
 * while Y goes under X). The trigger checks it again in the database, after locking the
 * parent row: the second request then waits for the first, sees its change, and fails.
 */
final class Version20261122165552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the parent of the categories; the existing ones stay top-level';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE categories ADD parent_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES categories (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_3AF34668727ACA70 ON categories (parent_id)');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT categories_not_own_parent_check CHECK (parent_id IS NULL OR parent_id <> id)');
        $this->addSql(<<<'SQL'
            CREATE FUNCTION categories_two_levels() RETURNS trigger AS $$
            BEGIN
                IF NEW.parent_id IS NOT NULL THEN
                    -- Waits for any request that is changing the parent, then reads it as committed.
                    PERFORM 1 FROM categories WHERE id = NEW.parent_id FOR UPDATE;
                    IF EXISTS (SELECT 1 FROM categories WHERE id = NEW.parent_id AND parent_id IS NOT NULL) THEN
                        RAISE EXCEPTION 'A child category cannot hold others.' USING ERRCODE = 'check_violation';
                    END IF;
                    IF EXISTS (SELECT 1 FROM categories WHERE parent_id = NEW.id) THEN
                        RAISE EXCEPTION 'A category that holds others must stay at the top level.' USING ERRCODE = 'check_violation';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);
        $this->addSql('CREATE TRIGGER categories_two_levels BEFORE INSERT OR UPDATE OF parent_id ON categories FOR EACH ROW EXECUTE FUNCTION categories_two_levels()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER categories_two_levels ON categories');
        $this->addSql('DROP FUNCTION categories_two_levels()');
        $this->addSql('ALTER TABLE categories DROP CONSTRAINT categories_not_own_parent_check');
        $this->addSql('ALTER TABLE categories DROP CONSTRAINT FK_3AF34668727ACA70');
        $this->addSql('DROP INDEX IDX_3AF34668727ACA70');
        $this->addSql('ALTER TABLE categories DROP parent_id');
    }
}
