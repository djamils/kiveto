<?php

declare(strict_types=1);

namespace DoctrineMigrations\Billing;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Invoice dispatches: why the asynchronous send skipped one (it then neither counts as a reminder nor holds back the next).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing__invoice_dispatches ADD skipped_reason VARCHAR(64) DEFAULT NULL AFTER notification_ids');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing__invoice_dispatches DROP skipped_reason');
    }
}
