<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scheduling: practitioner-day lock rows taken by every booking and planning block write (S4d).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE scheduling__practitioner_day_locks (
              clinic_id BINARY(16) NOT NULL,
              practitioner_user_id BINARY(16) NOT NULL,
              local_date DATE NOT NULL,
              INDEX idx_sched_day_lock_date (local_date),
              PRIMARY KEY (clinic_id, practitioner_user_id, local_date)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE scheduling__practitioner_day_locks');
    }
}
