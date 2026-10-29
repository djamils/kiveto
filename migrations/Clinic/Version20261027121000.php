<?php

declare(strict_types=1);

namespace DoctrineMigrations\Clinic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Daily count of the active veterinarians of each clinic (SMS quota).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE clinic__veterinarian_daily_counts (
              clinic_id BINARY(16) NOT NULL,
              day DATE NOT NULL,
              count INT NOT NULL,
              PRIMARY KEY (clinic_id, day)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE clinic__veterinarian_daily_counts');
    }
}
