<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scheduling: the client each appointment\'s confirmation or change went to (owner change → cancellation to the former owner).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling__appointment_notifications ADD notified_owner_id BINARY(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling__appointment_notifications DROP notified_owner_id');
    }
}
