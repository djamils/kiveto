<?php

declare(strict_types=1);

namespace DoctrineMigrations\Clinic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A membership records how much of a full-time week the member works.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinic_memberships ADD work_time_percent SMALLINT DEFAULT 100 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinic_memberships DROP work_time_percent');
    }
}
