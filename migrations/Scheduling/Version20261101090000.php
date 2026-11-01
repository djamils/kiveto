<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scheduling: pending online bookings (PENDING_CLINIC_CONFIRMATION, DECLINED), source, booking motif, who cancelled and why, pending expiry, silent confirmation.';
    }

    public function up(Schema $schema): void
    {
        // PENDING_CLINIC_CONFIRMATION has 27 characters
        $this->addSql('ALTER TABLE scheduling__appointments MODIFY status VARCHAR(32) NOT NULL');
        $this->addSql("ALTER TABLE scheduling__appointments ADD source VARCHAR(16) DEFAULT 'clinic' NOT NULL, ADD booking_motif_id BINARY(16) DEFAULT NULL, ADD cancelled_by VARCHAR(16) DEFAULT NULL, ADD cancellation_reason VARCHAR(32) DEFAULT NULL, ADD pending_expires_at DATETIME DEFAULT NULL, ADD confirmed_silently TINYINT DEFAULT 0 NOT NULL");
        // Every cancellation before online booking came from the clinic
        $this->addSql("UPDATE scheduling__appointments SET cancelled_by = 'clinic' WHERE status = 'CANCELLED'");
        $this->addSql('CREATE INDEX idx_status_pending_expires ON scheduling__appointments (status, pending_expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_status_pending_expires ON scheduling__appointments');
        $this->addSql('ALTER TABLE scheduling__appointments DROP source, DROP booking_motif_id, DROP cancelled_by, DROP cancellation_reason, DROP pending_expires_at, DROP confirmed_silently');
        $this->addSql('ALTER TABLE scheduling__appointments MODIFY status VARCHAR(20) NOT NULL');
    }
}
