<?php

declare(strict_types=1);

namespace DoctrineMigrations\Clinic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Let a clinic say when it opens and how it bills.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE
              clinic__clinics
            ADD
              opening_hours JSON NOT NULL,
            ADD
              billing_settings JSON NOT NULL
        SQL);

        // Existing clinics have said nothing yet, which is not the same as being
        // closed all week: an empty object reads back as "not filled in".
        $this->addSql("UPDATE clinic__clinics SET opening_hours = '{}', billing_settings = '{}'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinics DROP opening_hours, DROP billing_settings');
    }
}
