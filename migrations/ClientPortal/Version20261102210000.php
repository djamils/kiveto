<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClientPortal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ClientPortal: the questionnaires still unlocked, scanned by the lock sweep (S6-02); the Portal lock rows (S6-06, S7-02).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_questionnaire_unlocked ON client_portal__pre_visit_questionnaires (status, locked_at)');
        $this->addSql('CREATE TABLE client_portal__locks (lock_key VARCHAR(100) NOT NULL, PRIMARY KEY (lock_key)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client_portal__locks');
        $this->addSql('DROP INDEX idx_questionnaire_unlocked ON client_portal__pre_visit_questionnaires');
    }
}
