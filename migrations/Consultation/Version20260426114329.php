<?php

declare(strict_types=1);

namespace DoctrineMigrations\Consultation;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426114329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_waiting_entry ON consultation__consultations');
        $this->addSql('DROP INDEX idx_animal ON consultation__consultations');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              consultation__consultations
            ADD
              admission_id VARBINARY(16) NOT NULL,
            ADD
              patient_id VARBINARY(16) NOT NULL,
            DROP
              waiting_room_entry_id,
            DROP
              owner_id,
            DROP
              animal_id
        SQL);
        $this->addSql('CREATE INDEX idx_patient ON consultation__consultations (patient_id)');
        $this->addSql('CREATE INDEX idx_admission ON consultation__consultations (admission_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_patient ON consultation__consultations');
        $this->addSql('DROP INDEX idx_admission ON consultation__consultations');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              consultation__consultations
            ADD
              waiting_room_entry_id VARBINARY(16) DEFAULT NULL,
            ADD
              owner_id VARBINARY(16) DEFAULT NULL,
            ADD
              animal_id VARBINARY(16) DEFAULT NULL,
            DROP
              admission_id,
            DROP
              patient_id
        SQL);
        $this->addSql('CREATE INDEX idx_waiting_entry ON consultation__consultations (waiting_room_entry_id)');
        $this->addSql('CREATE INDEX idx_animal ON consultation__consultations (animal_id)');
    }
}
