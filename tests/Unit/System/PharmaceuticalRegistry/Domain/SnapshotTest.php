<?php

declare(strict_types=1);

namespace Tests\Unit\System\PharmaceuticalRegistry\Domain;

use App\System\PharmaceuticalRegistry\Domain\Event\SnapshotStarted;
use App\System\PharmaceuticalRegistry\Domain\Exception\InvalidSnapshotStatusTransitionException;
use App\System\PharmaceuticalRegistry\Domain\Exception\SnapshotAlreadyAppliedException;
use App\System\PharmaceuticalRegistry\Domain\Snapshot;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ContentHash;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportResult;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SnapshotId;
use PHPUnit\Framework\TestCase;

final class SnapshotTest extends TestCase
{
    public function testCreateRecordsSnapshotStarted(): void
    {
        $snapshot = $this->createSnapshot();
        $events   = $snapshot->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(SnapshotStarted::class, $events[0]);
    }

    public function testValidTransitions(): void
    {
        $now      = new \DateTimeImmutable();
        $snapshot = $this->createSnapshot();

        $snapshot->markAsRunning($now);
        $snapshot->markAsDiffed([]);
        $snapshot->markAsApplied(ImportResult::of(0, 0, 0, 0), $now);

        self::assertSame('APPLIED', $snapshot->status()->value);
    }

    public function testInvalidTransitionFromPendingToDiffed(): void
    {
        $snapshot = $this->createSnapshot();

        $this->expectException(InvalidSnapshotStatusTransitionException::class);
        $snapshot->markAsDiffed([]);
    }

    public function testMarkAsAppliedTwiceThrows(): void
    {
        $now      = new \DateTimeImmutable();
        $snapshot = $this->createSnapshot();
        $snapshot->markAsRunning($now);
        $snapshot->markAsDiffed([]);
        $snapshot->markAsApplied(ImportResult::of(0, 0, 0, 0), $now);

        $this->expectException(SnapshotAlreadyAppliedException::class);
        $snapshot->markAsApplied(ImportResult::of(0, 0, 0, 0), $now);
    }

    public function testCannotAddEntryAfterDiffed(): void
    {
        $now      = new \DateTimeImmutable();
        $snapshot = $this->createSnapshot();
        $snapshot->markAsRunning($now);
        $snapshot->markAsDiffed([]);

        $this->expectException(\DomainException::class);
        $snapshot->addEntry('FR/H/001', ContentHash::fromString(str_repeat('a', 64)), []);
    }

    private function createSnapshot(): Snapshot
    {
        return Snapshot::create(
            id: SnapshotId::fromString('018f1b1e-0000-7000-8000-000000000001'),
            source: ImportSource::ANMV,
            now: new \DateTimeImmutable(),
        );
    }
}
