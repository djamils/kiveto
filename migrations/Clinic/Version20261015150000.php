<?php

declare(strict_types=1);

namespace DoctrineMigrations\Clinic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A clinic records its regional council of the Order, its liability insurer, its colour and its logo.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinics
            ADD letterhead_ordinal_council VARCHAR(8) DEFAULT NULL,
            ADD letterhead_liability_insurer VARCHAR(80) DEFAULT NULL,
            ADD letterhead_liability_policy_number VARCHAR(40) DEFAULT NULL,
            ADD brand_color VARCHAR(7) DEFAULT NULL,
            ADD logo_key VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinics
            DROP letterhead_ordinal_council,
            DROP letterhead_liability_insurer,
            DROP letterhead_liability_policy_number,
            DROP brand_color,
            DROP logo_key');
    }
}
