<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make practitioner_user_id NOT NULL on scheduling__appointments';
    }

    public function up(Schema $schema): void
    {
        // Safety check: abort if any rows have NULL practitioner_user_id
        /** @var int|string $rawCount */
        $rawCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM scheduling__appointments WHERE practitioner_user_id IS NULL',
        );
        $count = (int) $rawCount;

        if ($count > 0) {
            throw new \RuntimeException(\sprintf(
                'Found %d appointments with NULL practitioner_user_id. '
                . 'Fix these rows before running this migration.',
                $count,
            ));
        }

        $this->addSql(<<<'SQL'
            ALTER TABLE scheduling__appointments MODIFY practitioner_user_id BINARY(16) NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE scheduling__appointments MODIFY practitioner_user_id BINARY(16) DEFAULT NULL
        SQL);
    }
}
