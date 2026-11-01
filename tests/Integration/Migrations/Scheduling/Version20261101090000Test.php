<?php

declare(strict_types=1);

namespace App\Tests\Integration\Migrations\Scheduling;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Scheduling\Version20261101090000;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * AC 143: the pending-booking migration on a scratch database holding
 * appointments booked before it — every cancellation came from the clinic,
 * every appointment from the clinic, and the status column takes the new
 * 27-character status.
 */
final class Version20261101090000Test extends KernelTestCase
{
    private const string DATABASE = 'kiveto_migration_scheduling';

    private Connection $scratch;

    public static function setUpBeforeClass(): void
    {
        // Migrations are loaded by path, not autoloaded.
        require_once __DIR__ . '/../../../../migrations/Scheduling/Version20261101090000.php';
    }

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $params = $default->getParams();
        unset($params['dbname']);

        $server = DriverManager::getConnection($params);
        $server->executeStatement('DROP DATABASE IF EXISTS ' . self::DATABASE);
        $server->executeStatement('CREATE DATABASE ' . self::DATABASE);
        $server->close();

        $this->scratch = DriverManager::getConnection([...$params, 'dbname' => self::DATABASE]);

        // The table as it stood before the migration
        $this->scratch->executeStatement(<<<'SQL'
            CREATE TABLE scheduling__appointments (
                id BINARY(16) NOT NULL,
                clinic_id BINARY(16) NOT NULL,
                practitioner_user_id BINARY(16) NOT NULL,
                starts_at_utc DATETIME NOT NULL,
                duration_minutes INT NOT NULL,
                status VARCHAR(20) NOT NULL,
                reason LONGTEXT DEFAULT NULL,
                notes LONGTEXT DEFAULT NULL,
                service_started_at_utc DATETIME DEFAULT NULL,
                created_at_utc DATETIME NOT NULL,
                updated_at_utc DATETIME NOT NULL,
                linked_admission_id BINARY(16) DEFAULT NULL,
                owner_id BINARY(16) DEFAULT NULL,
                animal_id BINARY(16) DEFAULT NULL,
                version INT DEFAULT 1 NOT NULL,
                PRIMARY KEY(id),
                INDEX idx_status (status)
            )
            SQL);
    }

    protected function tearDown(): void
    {
        $this->scratch->executeStatement('DROP DATABASE ' . self::DATABASE);
        $this->scratch->close();

        parent::tearDown();
    }

    public function testExistingAppointmentsAreClinicBookingsAndCancellations(): void
    {
        $this->appointment('a1', 'CANCELLED');
        $this->appointment('a2', 'PLANNED');
        $this->appointment('a3', 'COMPLETED');

        $migration = new Version20261101090000($this->scratch, new NullLogger());
        $migration->up(new Schema());

        foreach ($migration->getSql() as $query) {
            $this->scratch->executeStatement($query->getStatement());
        }

        $rows = $this->scratch->fetchAllAssociative('SELECT status, source, cancelled_by, confirmed_silently, pending_expires_at, booking_motif_id FROM scheduling__appointments ORDER BY status');

        self::assertSame([
            ['CANCELLED', 'clinic', 'clinic', 0, null, null],
            ['COMPLETED', 'clinic', null, 0, null, null],
            ['PLANNED', 'clinic', null, 0, null, null],
        ], array_map(static fn (array $row): array => [
            RowAccessor::string($row, 'status'),
            RowAccessor::string($row, 'source'),
            RowAccessor::nullableString($row, 'cancelled_by'),
            RowAccessor::int($row, 'confirmed_silently'),
            RowAccessor::nullableString($row, 'pending_expires_at'),
            RowAccessor::nullableString($row, 'booking_motif_id'),
        ], $rows));

        $length = $this->scratch->fetchOne(
            "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'scheduling__appointments' AND COLUMN_NAME = 'status'",
            ['db' => self::DATABASE],
        );
        self::assertIsNumeric($length);
        self::assertGreaterThanOrEqual(27, (int) $length);

        $index = $this->scratch->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'scheduling__appointments' AND INDEX_NAME = 'idx_status_pending_expires' ORDER BY SEQ_IN_INDEX",
            ['db' => self::DATABASE],
        );
        self::assertSame(['status', 'pending_expires_at'], $index);
    }

    private function appointment(string $id, string $status): void
    {
        $this->scratch->insert('scheduling__appointments', [
            'id'                   => str_pad($id, 16, "\0"),
            'clinic_id'            => str_pad('c1', 16, "\0"),
            'practitioner_user_id' => str_pad('u1', 16, "\0"),
            'starts_at_utc'        => '2026-10-01 09:00:00',
            'duration_minutes'     => 30,
            'status'               => $status,
            'created_at_utc'       => '2026-09-01 09:00:00',
            'updated_at_utc'       => '2026-09-01 09:00:00',
        ]);
    }
}
