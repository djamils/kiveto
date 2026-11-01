<?php

declare(strict_types=1);

namespace DoctrineMigrations\Client;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Client consent history: one row per consent kind and channel granted or withdrawn, existing consents taken over as approximate grants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client__consent_history (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              client_id BINARY(16) NOT NULL,
              kind VARCHAR(30) NOT NULL,
              channel VARCHAR(10) NOT NULL,
              action VARCHAR(10) NOT NULL,
              at DATETIME NOT NULL,
              source VARCHAR(10) NOT NULL,
              actor_type VARCHAR(20) NOT NULL,
              actor_id BINARY(16) DEFAULT NULL,
              author_label VARCHAR(255) NOT NULL,
              wording_version VARCHAR(50) DEFAULT NULL,
              approximate TINYINT(1) DEFAULT 0 NOT NULL,
              INDEX idx_client_consent_history_client (clinic_id, client_id, at, id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

        // Every channel of every consent on file becomes one approximate grant
        // dated by its grantedAt (an upper bound: a consent is re-dated when a
        // channel is added). A NULL column or a document of another shape
        // gives no row; the date is converted to UTC from its offset.
        $this->addSql(<<<'SQL'
            INSERT INTO client__consent_history
              (id, clinic_id, client_id, kind, channel, action, at, source, actor_type, actor_id, author_label, wording_version, approximate)
            SELECT UUID_TO_BIN(UUID()), c.clinic_id, c.id, jt.kind, jt.channel, 'granted',
                   CONVERT_TZ(STR_TO_DATE(LEFT(jt.granted_at, 19), '%Y-%m-%dT%H:%i:%s'), SUBSTRING(jt.granted_at, 20), '+00:00'),
                   'system', 'system', NULL, 'Reprise', NULL, 1
            FROM client__clients c,
                 JSON_TABLE(c.communication_preferences, '$.consents[*]' COLUMNS (
                   kind VARCHAR(30) PATH '$.kind' NULL ON ERROR,
                   granted_at VARCHAR(40) PATH '$.grantedAt' NULL ON ERROR,
                   NESTED PATH '$.channels[*]' COLUMNS (channel VARCHAR(10) PATH '$' NULL ON ERROR)
                 )) jt
            WHERE c.communication_preferences IS NOT NULL
              AND jt.kind IN ('appointment_reminders', 'vaccination_reminders', 'clinical_documents', 'billing', 'marketing')
              AND jt.channel IN ('sms', 'email')
              AND jt.granted_at REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}[+-][0-9]{2}:[0-9]{2}$'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client__consent_history');
    }
}
