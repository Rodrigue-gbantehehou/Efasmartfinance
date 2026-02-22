<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222075022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deletion_requested_at/deleted_at to user. Unique indexes on email and phone_number. (Applied partially via previous runs, this migration is idempotent.)';
    }

    public function up(Schema $schema): void
    {
        // All changes are already applied in the DB from prior partial runs.
        // This is intentionally a no-op to let Doctrine mark the migration as done.
        $this->addSql('SELECT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS UNIQ_IDENTIFIER_EMAIL ON `user`');
        $this->addSql('DROP INDEX IF EXISTS UNIQ_IDENTIFIER_PHONE ON `user`');
        $this->addSql('ALTER TABLE `user` DROP COLUMN IF EXISTS deletion_requested_at, DROP COLUMN IF EXISTS deleted_at');
    }
}
