<?php

declare(strict_types=1);

namespace DoctrineMigrations\IdentityAccess;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IdentityAccess: onboarding grants get a purpose (invitation|registration) and a consumption date.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_access__otp_challenges ADD onboarding_purpose VARCHAR(16) DEFAULT NULL, ADD onboarding_consumed_at DATETIME DEFAULT NULL');
        // Every grant minted so far opened invitations.
        $this->addSql("UPDATE identity_access__otp_challenges SET onboarding_purpose = 'invitation' WHERE onboarding_token_hash IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_access__otp_challenges DROP onboarding_purpose, DROP onboarding_consumed_at');
    }
}
