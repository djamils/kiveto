<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Idempotency;

use App\Shared\Domain\Idempotency\IdempotencyKey;
use App\Shared\Domain\Idempotency\IdempotencyKeyReusedException;
use App\Shared\Domain\Idempotency\IdempotencyStoreInterface;
use App\Shared\Domain\Idempotency\RequestFingerprint;
use App\Shared\Domain\Idempotency\StoredResponse;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Raw DBAL store on `shared__idempotency_keys` (MySQL). Each (principal, key)
 * is guarded by the shared DBAL lock `idempotency:<principal>:<key>`, so two
 * identical requests in flight run the operation once. Expired rows are purged
 * opportunistically, 500 at a time, on every write.
 */
final readonly class DbalIdempotencyStore implements IdempotencyStoreInterface
{
    private const string TABLE = 'shared__idempotency_keys';

    private const int PURGE_BATCH = 500;

    /** Lock TTL in seconds: covers the longest write the operation may run. */
    private const float LOCK_TTL = 60.0;

    public function __construct(
        private Connection $connection,
        private LockFactory $lockFactory,
        private ClockInterface $clock,
    ) {
    }

    public function execute(
        string $principalId,
        IdempotencyKey $key,
        RequestFingerprint $fingerprint,
        \Closure $operation,
    ): StoredResponse {
        $principal = Uuid::fromString($principalId);

        $lock = $this->lockFactory->createLock(
            \sprintf('idempotency:%s:%s', $principal->toRfc4122(), $key->toString()),
            self::LOCK_TTL,
        );
        $lock->acquire(true);

        try {
            $recorded = $this->findLive($principal, $key);

            if (null !== $recorded) {
                if (!$fingerprint->equals($recorded['fingerprint'])) {
                    throw new IdempotencyKeyReusedException($key);
                }

                return $recorded['response'];
            }

            $response = $operation();
            $this->record($principal, $key, $fingerprint, $response);

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{fingerprint: RequestFingerprint, response: StoredResponse}|null
     */
    private function findLive(Uuid $principal, IdempotencyKey $key): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT request_fingerprint, response_status, response_body FROM ' . self::TABLE
            . ' WHERE principal_id = :principal AND idempotency_key = :key AND expires_at > :now',
            [
                'principal' => $principal->toBinary(),
                'key'       => $key->toString(),
                'now'       => $this->utc($this->clock->now()),
            ],
        );

        if (false === $row) {
            return null;
        }

        return [
            'fingerprint' => RequestFingerprint::fromHash(RowAccessor::string($row, 'request_fingerprint')),
            'response'    => StoredResponse::create(
                RowAccessor::int($row, 'response_status'),
                RowAccessor::nullableString($row, 'response_body'),
            ),
        ];
    }

    private function record(Uuid $principal, IdempotencyKey $key, RequestFingerprint $fingerprint, StoredResponse $response): void
    {
        $now = $this->clock->now();

        $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE expires_at < :now LIMIT ' . self::PURGE_BATCH,
            ['now' => $this->utc($now)],
        );

        // An expired row for the same (principal, key) may still be there when
        // the purge batch did not reach it: overwrite it.
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE
            . ' (principal_id, idempotency_key, request_fingerprint, response_status, response_body, created_at, expires_at)'
            . ' VALUES (:principal, :key, :fingerprint, :status, :body, :created, :expires)'
            . ' ON DUPLICATE KEY UPDATE request_fingerprint = VALUES(request_fingerprint),'
            . ' response_status = VALUES(response_status), response_body = VALUES(response_body),'
            . ' created_at = VALUES(created_at), expires_at = VALUES(expires_at)',
            [
                'principal'   => $principal->toBinary(),
                'key'         => $key->toString(),
                'fingerprint' => $fingerprint->toString(),
                'status'      => $response->status(),
                'body'        => $response->body(),
                'created'     => $this->utc($now),
                'expires'     => $this->utc($now->modify(\sprintf('+%d hours', self::RETENTION_HOURS))),
            ],
        );
    }

    private function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
