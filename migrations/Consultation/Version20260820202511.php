<?php

declare(strict_types=1);

namespace DoctrineMigrations\Consultation;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820202511 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cockpit child tables (motifs, typed vitals, exam systems, diagnoses, plan actions, '
            . 'prescription and billing lines) and the SOAP free-text columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE consultation__billing_lines (
              id VARBINARY(16) NOT NULL,
              consultation_id VARBINARY(16) NOT NULL,
              source_line_id VARBINARY(16) NOT NULL,
              source VARCHAR(20) NOT NULL,
              label VARCHAR(255) NOT NULL,
              code VARCHAR(40) DEFAULT NULL,
              quantity NUMERIC(10, 2) NOT NULL,
              unit_price_minor_units INT NOT NULL,
              currency VARCHAR(3) NOT NULL,
              tax_category_code VARCHAR(40) DEFAULT NULL,
              position SMALLINT NOT NULL,
              INDEX idx_consultation_billing_line (consultation_id, position),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE consultation__diagnoses (
              id VARBINARY(16) NOT NULL,
              consultation_id VARBINARY(16) NOT NULL,
              code VARCHAR(40) DEFAULT NULL,
              label VARCHAR(255) NOT NULL,
              certainty VARCHAR(20) NOT NULL,
              note LONGTEXT DEFAULT NULL,
              is_primary TINYINT NOT NULL,
              source VARCHAR(20) NOT NULL,
              created_at_utc DATETIME NOT NULL,
              created_by_user_id VARBINARY(16) NOT NULL,
              position SMALLINT NOT NULL,
              INDEX idx_consultation_diagnosis (consultation_id, position),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE consultation__exam_systems (
              id VARBINARY(16) NOT NULL,
              consultation_id VARBINARY(16) NOT NULL,
              `system` VARCHAR(40) NOT NULL,
              status VARCHAR(20) NOT NULL,
              notes LONGTEXT DEFAULT NULL,
              structured_data JSON NOT NULL,
              recorded_at_utc DATETIME NOT NULL,
              recorded_by_user_id VARBINARY(16) NOT NULL,
              position SMALLINT NOT NULL,
              INDEX idx_consultation_exam_system (consultation_id, position),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE consultation__motifs (
              id VARBINARY(16) NOT NULL,
              consultation_id VARBINARY(16) NOT NULL,
              label VARCHAR(120) NOT NULL,
              position SMALLINT NOT NULL,
              INDEX idx_consultation_motif (consultation_id, position),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE consultation__plan_actions (
              id VARBINARY(16) NOT NULL,
              consultation_id VARBINARY(16) NOT NULL,
              kind VARCHAR(30) NOT NULL,
              description VARCHAR(255) NOT NULL,
              catalog_code VARCHAR(40) DEFAULT NULL,
              posology VARCHAR(255) DEFAULT NULL,
              duration_days INT DEFAULT NULL,
              follow_up_days INT DEFAULT NULL,
              quantity NUMERIC(10, 2) NOT NULL,
              unit_price_minor_units INT DEFAULT NULL,
              currency VARCHAR(3) DEFAULT NULL,
              tax_category_code VARCHAR(40) DEFAULT NULL,
              created_at_utc DATETIME NOT NULL,
              created_by_user_id VARBINARY(16) NOT NULL,
              position SMALLINT NOT NULL,
              INDEX idx_consultation_plan_action (consultation_id, position),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE consultation__prescription_lines (
              id VARBINARY(16) NOT NULL,
              consultation_id VARBINARY(16) NOT NULL,
              article_id VARBINARY(16) DEFAULT NULL,
              code VARCHAR(40) DEFAULT NULL,
              label VARCHAR(255) NOT NULL,
              dose VARCHAR(60) DEFAULT NULL,
              frequency VARCHAR(60) DEFAULT NULL,
              duration_days INT DEFAULT NULL,
              route VARCHAR(60) DEFAULT NULL,
              quantity NUMERIC(10, 2) NOT NULL,
              unit_price_minor_units INT NOT NULL,
              currency VARCHAR(3) NOT NULL,
              tax_category_code VARCHAR(40) DEFAULT NULL,
              created_at_utc DATETIME NOT NULL,
              created_by_user_id VARBINARY(16) NOT NULL,
              position SMALLINT NOT NULL,
              INDEX idx_consultation_prescription_line (consultation_id, position),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE consultation__typed_vitals (
              id VARBINARY(16) NOT NULL,
              consultation_id VARBINARY(16) NOT NULL,
              type VARCHAR(40) NOT NULL,
              value VARCHAR(60) NOT NULL,
              recorded_at_utc DATETIME NOT NULL,
              recorded_by_user_id VARBINARY(16) NOT NULL,
              position SMALLINT NOT NULL,
              INDEX idx_consultation_typed_vital (consultation_id, position),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              consultation__consultations
            ADD
              subjective_text LONGTEXT DEFAULT NULL,
            ADD
              objective_observations LONGTEXT DEFAULT NULL,
            ADD
              team_memo LONGTEXT DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE consultation__billing_lines');
        $this->addSql('DROP TABLE consultation__diagnoses');
        $this->addSql('DROP TABLE consultation__exam_systems');
        $this->addSql('DROP TABLE consultation__motifs');
        $this->addSql('DROP TABLE consultation__plan_actions');
        $this->addSql('DROP TABLE consultation__prescription_lines');
        $this->addSql('DROP TABLE consultation__typed_vitals');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              consultation__consultations
            DROP
              subjective_text,
            DROP
              objective_observations,
            DROP
              team_memo
        SQL);
    }
}
