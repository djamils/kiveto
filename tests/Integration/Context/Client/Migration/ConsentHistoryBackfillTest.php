<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Migration;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Client\Version20261101130000;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * AC 53: the consent history migration on a scratch database holding
 * clients created before it — one approximate `granted` row per channel of
 * every consent on file, dated by its grantedAt in UTC, signed « Reprise »
 * by the system; a NULL column or a document of another shape gives none.
 */
final class ConsentHistoryBackfillTest extends KernelTestCase
{
    private const string DATABASE = 'kiveto_migration_client_consents';

    private Connection $scratch;

    public static function setUpBeforeClass(): void
    {
        // Migrations are loaded by path, not autoloaded.
        require_once __DIR__ . '/../../../../../migrations/Client/Version20261101130000.php';
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

        // The columns of the client table the migration reads.
        $this->scratch->executeStatement(<<<'SQL'
            CREATE TABLE client__clients (
                id BINARY(16) NOT NULL,
                clinic_id BINARY(16) NOT NULL,
                communication_preferences JSON DEFAULT NULL,
                PRIMARY KEY(id)
            )
            SQL);
    }

    protected function tearDown(): void
    {
        $this->scratch->executeStatement('DROP DATABASE ' . self::DATABASE);
        $this->scratch->close();

        parent::tearDown();
    }

    public function testEveryChannelOnFileBecomesOneApproximateGrant(): void
    {
        $this->client('lambert', [
            'preferredChannel' => 'sms',
            'consents'         => [
                ['kind' => 'marketing', 'channels' => ['sms', 'email'], 'grantedAt' => '2026-09-30T09:00:00+02:00'],
                ['kind' => 'billing', 'channels' => ['email'], 'grantedAt' => '2025-01-01T10:00:00+00:00'],
            ],
        ]);
        $this->client('durand', null);
        $this->client('no-consent', ['preferredChannel' => 'phone', 'consents' => []]);

        $this->migrate();

        self::assertSame([
            ['lambert', 'billing', 'email', '2025-01-01 10:00:00'],
            ['lambert', 'marketing', 'email', '2026-09-30 07:00:00'],
            ['lambert', 'marketing', 'sms', '2026-09-30 07:00:00'],
        ], $this->rows());

        $row = $this->scratch->fetchAssociative('SELECT clinic_id, action, source, actor_type, actor_id, author_label, wording_version, approximate FROM client__consent_history LIMIT 1');
        self::assertIsArray($row);
        self::assertSame(str_pad('clinic', 16, "\0"), $row['clinic_id']);
        self::assertSame(
            ['granted', 'system', 'system', null, 'Reprise', null, 1],
            [
                RowAccessor::string($row, 'action'),
                RowAccessor::string($row, 'source'),
                RowAccessor::string($row, 'actor_type'),
                RowAccessor::nullableString($row, 'actor_id'),
                RowAccessor::string($row, 'author_label'),
                RowAccessor::nullableString($row, 'wording_version'),
                RowAccessor::int($row, 'approximate'),
            ],
        );
    }

    public function testADocumentOfAnotherShapeGivesNoRow(): void
    {
        $this->client('a-string', 'oops');
        $this->client('json-null', 'null-literal');
        $this->client('consents-string', ['consents' => 'marketing']);
        $this->client('consents-object', ['consents' => ['kind' => 'marketing', 'channels' => ['sms'], 'grantedAt' => '2026-01-01T00:00:00+00:00']]);
        $this->client('channels-string', ['consents' => [['kind' => 'marketing', 'channels' => 'sms', 'grantedAt' => '2026-01-01T00:00:00+00:00']]]);
        $this->client('unknown-kind', ['consents' => [['kind' => 'telepathy', 'channels' => ['sms'], 'grantedAt' => '2026-01-01T00:00:00+00:00']]]);
        $this->client('phone-channel', ['consents' => [['kind' => 'marketing', 'channels' => ['phone'], 'grantedAt' => '2026-01-01T00:00:00+00:00']]]);
        $this->client('no-date', ['consents' => [['kind' => 'marketing', 'channels' => ['sms']]]]);
        $this->client('garbage-date', ['consents' => [['kind' => 'marketing', 'channels' => ['sms'], 'grantedAt' => 'hier']]]);
        $this->client('one-good-one-bad', ['consents' => [
            ['kind' => 'marketing', 'channels' => ['sms', 'mail'], 'grantedAt' => '2026-01-01T00:00:00+00:00'],
            ['kind' => 'billing', 'channels' => [], 'grantedAt' => '2026-01-01T00:00:00+00:00'],
        ]]);

        $this->migrate();

        self::assertSame([['one-good-one-bad', 'marketing', 'sms', '2026-01-01 00:00:00']], $this->rows());
    }

    private function migrate(): void
    {
        $migration = new Version20261101130000($this->scratch, new NullLogger());
        $migration->up(new Schema());

        foreach ($migration->getSql() as $query) {
            $this->scratch->executeStatement($query->getStatement());
        }
    }

    /**
     * @param array<string, mixed>|string|null $preferences a document, a raw JSON scalar ('oops', 'null-literal'), or NULL
     */
    private function client(string $id, array|string|null $preferences): void
    {
        $json = match (true) {
            null === $preferences           => null,
            'null-literal' === $preferences => 'null',
            \is_string($preferences)        => json_encode($preferences, \JSON_THROW_ON_ERROR),
            default                         => json_encode($preferences, \JSON_THROW_ON_ERROR),
        };

        $this->scratch->insert('client__clients', [
            'id'                        => str_pad($id, 16, "\0"),
            'clinic_id'                 => str_pad('clinic', 16, "\0"),
            'communication_preferences' => $json,
        ]);
    }

    /**
     * @return list<array{string, string, string, string}>
     */
    private function rows(): array
    {
        $rows = $this->scratch->fetchAllAssociative('SELECT TRIM(TRAILING CHAR(0) FROM client_id) AS client, kind, channel, at FROM client__consent_history ORDER BY client, kind, channel');

        return array_map(static fn (array $row): array => [
            RowAccessor::string($row, 'client'),
            RowAccessor::string($row, 'kind'),
            RowAccessor::string($row, 'channel'),
            RowAccessor::string($row, 'at'),
        ], $rows);
    }
}
