<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Idempotency;

use App\Shared\Domain\Idempotency\IdempotencyKey;
use App\Shared\Domain\Idempotency\IdempotencyKeyReusedException;
use App\Shared\Domain\Idempotency\IdempotencyStoreInterface;
use App\Shared\Domain\Idempotency\RequestFingerprint;
use App\Shared\Domain\Idempotency\StoredResponse;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Idempotency\DbalIdempotencyStore;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Uid\Uuid;

final class DbalIdempotencyStoreTest extends KernelTestCase
{
    private const string PRINCIPAL = '01960000-0000-7000-8000-0000000c0001';
    private const string OTHER     = '01960000-0000-7000-8000-0000000c0002';

    private Connection $connection;
    private LockFactory $locks;
    private \DateTimeImmutable $now;
    private DbalIdempotencyStore $store;
    private int $runs = 0;

    protected function setUp(): void
    {
        $container = self::getContainer();

        $connection = $container->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;

        $locks = $container->get('test.shared.lock.factory');
        \assert($locks instanceof LockFactory);
        $this->locks = $locks;

        $this->now = new \DateTimeImmutable('2026-10-29 10:00:00', new \DateTimeZone('UTC'));
        $test      = $this;
        $clock     = new class($test) implements ClockInterface {
            public function __construct(private readonly DbalIdempotencyStoreTest $test)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return $this->test->now();
            }
        };

        $this->store = new DbalIdempotencyStore($this->connection, $this->locks, $clock);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function testTheContainerWiresTheDbalStore(): void
    {
        self::assertInstanceOf(DbalIdempotencyStore::class, self::getContainer()->get(IdempotencyStoreInterface::class));
    }

    public function testTheFirstCallRunsTheOperationAndRecordsItsResponse(): void
    {
        $response = $this->execute('key-1', 'request', StoredResponse::create(201, '{"id": 1}'));

        self::assertSame(201, $response->status());
        self::assertSame('{"id": 1}', $response->body());
        self::assertSame(1, $this->runs);

        $row = $this->connection->fetchAssociative('SELECT * FROM shared__idempotency_keys');
        self::assertIsArray($row);
        self::assertSame(Uuid::fromString(self::PRINCIPAL)->toBinary(), $row['principal_id']);
        self::assertSame('key-1', $row['idempotency_key']);
        self::assertSame(hash('sha256', 'request'), $row['request_fingerprint']);
        self::assertSame(201, (int) (\is_scalar($row['response_status']) ? $row['response_status'] : 0));
        self::assertSame('2026-10-29 10:00:00', $row['created_at']);
        self::assertSame('2026-10-30 10:00:00', $row['expires_at']);
    }

    public function testARetryWithTheSameRequestReplaysWithoutRunningTheOperation(): void
    {
        $this->execute('key-1', 'request', StoredResponse::create(201, '{"id": 1}'));
        $this->now = $this->now->modify('+23 hours 59 minutes');

        $replayed = $this->execute('key-1', 'request', StoredResponse::create(500, 'never'));

        self::assertSame(201, $replayed->status());
        self::assertSame('{"id": 1}', $replayed->body());
        self::assertSame(1, $this->runs);
    }

    public function testAnEmptyBodyIsReplayedAsNull(): void
    {
        $this->execute('key-1', 'request', StoredResponse::create(204, null));

        $replayed = $this->execute('key-1', 'request', StoredResponse::create(500, 'never'));

        self::assertSame(204, $replayed->status());
        self::assertNull($replayed->body());
        self::assertSame(1, $this->runs);
    }

    public function testTheSameKeyWithAnotherRequestIsRejected(): void
    {
        $this->execute('key-1', 'request', StoredResponse::create(201, '{"id": 1}'));

        try {
            $this->execute('key-1', 'another request', StoredResponse::create(201, '{"id": 2}'));
            self::fail('A reused key must be rejected.');
        } catch (IdempotencyKeyReusedException $exception) {
            self::assertSame('The idempotency key "key-1" was already used for a different request.', $exception->getMessage());
        }

        self::assertSame(1, $this->runs);
        // The lock was released despite the exception.
        self::assertSame(201, $this->execute('key-1', 'request', StoredResponse::create(500, 'never'))->status());
    }

    public function testKeysAreScopedPerPrincipal(): void
    {
        $this->execute('key-1', 'request', StoredResponse::create(201, '{"id": 1}'));

        $response = $this->execute('key-1', 'another request', StoredResponse::create(201, '{"id": 2}'), self::OTHER);

        self::assertSame('{"id": 2}', $response->body());
        self::assertSame(2, $this->runs);
    }

    public function testAnExpiredRecordIsIgnoredAndOverwritten(): void
    {
        $this->execute('key-1', 'request', StoredResponse::create(201, '{"id": 1}'));
        $this->now = $this->now->modify('+24 hours');

        $response = $this->execute('key-1', 'another request', StoredResponse::create(200, '{"id": 2}'));

        self::assertSame(200, $response->status());
        self::assertSame(2, $this->runs);
        self::assertSame(1, $this->storedRows());
        self::assertSame(hash('sha256', 'another request'), $this->connection->fetchOne('SELECT request_fingerprint FROM shared__idempotency_keys'));
    }

    public function testEachWritePurgesExpiredRecords(): void
    {
        $this->execute('key-1', 'request', StoredResponse::create(201, null));
        $this->execute('key-2', 'request', StoredResponse::create(201, null), self::OTHER);
        $this->now = $this->now->modify('+2 hours');
        $this->execute('key-3', 'request', StoredResponse::create(201, null));
        self::assertSame(3, $this->storedRows());

        $this->now = $this->now->modify('+23 hours');
        $this->execute('key-4', 'request', StoredResponse::create(201, null));

        self::assertSame(['key-3', 'key-4'], $this->connection->fetchFirstColumn('SELECT idempotency_key FROM shared__idempotency_keys ORDER BY idempotency_key'));
    }

    public function testAFailingOperationRecordsNothingAndReleasesTheLock(): void
    {
        try {
            $this->store->execute(self::PRINCIPAL, IdempotencyKey::fromString('key-1'), RequestFingerprint::of('request'), static function (): StoredResponse {
                throw new \RuntimeException('boom');
            });
            self::fail('The operation exception must bubble up.');
        } catch (\RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
        }

        self::assertSame(0, $this->storedRows());
        self::assertSame(201, $this->execute('key-1', 'request', StoredResponse::create(201, null))->status());
        self::assertSame(1, $this->runs);
    }

    public function testTheOperationRunsUnderTheKeyLock(): void
    {
        $this->store->execute(self::PRINCIPAL, IdempotencyKey::fromString('key-1'), RequestFingerprint::of('request'), function (): StoredResponse {
            $concurrent = $this->locks->createLock('idempotency:' . self::PRINCIPAL . ':key-1');
            self::assertFalse($concurrent->acquire(false));

            return StoredResponse::create(201, null);
        });

        $after = $this->locks->createLock('idempotency:' . self::PRINCIPAL . ':key-1');
        self::assertTrue($after->acquire(false));
        $after->release();
    }

    public function testAnInvalidPrincipalIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->execute('key-1', 'request', StoredResponse::create(201, null), 'not-a-uuid');
    }

    private function execute(string $key, string $request, StoredResponse $response, string $principal = self::PRINCIPAL): StoredResponse
    {
        return $this->store->execute(
            $principal,
            IdempotencyKey::fromString($key),
            RequestFingerprint::of($request),
            function () use ($response): StoredResponse {
                ++$this->runs;

                return $response;
            },
        );
    }

    private function storedRows(): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM shared__idempotency_keys');

        return (int) (\is_scalar($count) ? $count : 0);
    }
}
