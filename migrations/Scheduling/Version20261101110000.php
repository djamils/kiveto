<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scheduling: when the decline of a pending appointment was told to the client (sent once, S4e).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling__appointment_notifications ADD decline_notified_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling__appointment_notifications DROP decline_notified_at');
    }
}
