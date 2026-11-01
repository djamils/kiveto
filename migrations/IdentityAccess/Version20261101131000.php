<?php

declare(strict_types=1);

namespace DoctrineMigrations\IdentityAccess;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101131000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IdentityAccess: one-time code challenges bound to a subject (invitation or user) and carrying the e-mail they verify.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_access__otp_challenges RENAME COLUMN invitation_ref TO subject_ref');
        $this->addSql('ALTER TABLE identity_access__otp_challenges ADD subject_type VARCHAR(20) DEFAULT NULL AFTER purpose, ADD target_email VARCHAR(180) DEFAULT NULL AFTER subject_ref');
        $this->addSql("UPDATE identity_access__otp_challenges SET subject_type = 'invitation' WHERE subject_ref IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        // Action challenges have no invitation: they cannot survive the old schema.
        $this->addSql('DELETE FROM identity_access__otp_challenges WHERE subject_type = \'user\'');
        $this->addSql('ALTER TABLE identity_access__otp_challenges DROP subject_type, DROP target_email');
        $this->addSql('ALTER TABLE identity_access__otp_challenges RENAME COLUMN subject_ref TO invitation_ref');
    }
}
