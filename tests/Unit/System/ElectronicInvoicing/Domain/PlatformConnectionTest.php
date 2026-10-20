<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Domain;

use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Domain\Connection\OnboardingState;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformCode;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnection;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionId;
use App\System\ElectronicInvoicing\Domain\Connection\SyncCursor;
use App\System\ElectronicInvoicing\Domain\Event\PlatformConnectionActivated;
use App\System\ElectronicInvoicing\Domain\Exception\InvalidConnectionTransitionException;
use PHPUnit\Framework\TestCase;

final class PlatformConnectionTest extends TestCase
{
    public function testAnOpenedConnectionIsPendingAndNotPolled(): void
    {
        $connection = $this->open();

        self::assertSame(OnboardingState::PENDING, $connection->state());
        self::assertFalse($connection->acceptsSubmissions());
        self::assertFalse($connection->isPolled());
        self::assertSame('812456903', $connection->organisationId()->value());
        self::assertSame('simulator', $connection->platform()->toString());
        self::assertSame('2026-10-21 10:00', $connection->cursor()->updatedAfter()->format('Y-m-d H:i'));
        self::assertSame('', $connection->cursor()->lastFlowId());
        self::assertSame('2026-10-21 10:00', $connection->openedAt()->format('Y-m-d H:i'));
        self::assertNull($connection->verifiedAt());
        self::assertNull($connection->rejectionReason());
        self::assertSame('01960000-0000-7000-8000-0000000ec001', $connection->id()->toString());
    }

    public function testVerifyingActivatesIt(): void
    {
        $connection = $this->open();
        $connection->markVerified(new \DateTimeImmutable('2026-10-21 11:00'));

        self::assertTrue($connection->acceptsSubmissions());
        self::assertTrue($connection->isPolled());
        self::assertNotNull($connection->verifiedAt());

        $event = $connection->pullDomainEvents()[0];
        self::assertInstanceOf(PlatformConnectionActivated::class, $event);
        self::assertSame('812456903', $event->organisationId);
        self::assertSame('electronic_invoicing.platform-connection.activated.v1', $event->name());
        self::assertSame($connection->id()->toString(), $event->aggregateId());
        self::assertSame('812456903', $event->payload()['organisationId']);
    }

    public function testARejectedConnectionCannotBeVerified(): void
    {
        $connection = $this->open();
        $connection->markRejected('KYC incomplete');

        self::assertSame(OnboardingState::REJECTED, $connection->state());
        self::assertSame('KYC incomplete', $connection->rejectionReason());

        $this->expectException(InvalidConnectionTransitionException::class);

        $connection->markVerified(new \DateTimeImmutable());
    }

    public function testAVerifiedConnectionCannotBeRejected(): void
    {
        $connection = $this->open();
        $connection->markVerified(new \DateTimeImmutable());

        $this->expectException(InvalidConnectionTransitionException::class);

        $connection->markRejected('late');
    }

    public function testTheCursorNeverMovesBackwards(): void
    {
        $connection = $this->open();
        $later      = SyncCursor::at(new \DateTimeImmutable('2026-10-21 12:00'), 'flow-b');

        $connection->advanceCursor($later);
        self::assertTrue($connection->cursor()->equals($later));

        $connection->advanceCursor(SyncCursor::at(new \DateTimeImmutable('2026-10-21 12:00'), 'flow-a'));
        $connection->advanceCursor(SyncCursor::at(new \DateTimeImmutable('2026-10-21 11:00'), 'flow-z'));
        self::assertTrue($connection->cursor()->equals($later));

        $connection->advanceCursor(SyncCursor::at(new \DateTimeImmutable('2026-10-21 12:00'), 'flow-c'));
        self::assertSame('flow-c', $connection->cursor()->lastFlowId());
    }

    public function testTheCursorOrdersFlowsSharingATimestampById(): void
    {
        $cursor = SyncCursor::at(new \DateTimeImmutable('2026-10-21 12:00'), 'flow-b');

        self::assertTrue($cursor->precedes(new \DateTimeImmutable('2026-10-21 12:00'), 'flow-c'));
        self::assertFalse($cursor->precedes(new \DateTimeImmutable('2026-10-21 12:00'), 'flow-b'));
        self::assertTrue($cursor->precedes(new \DateTimeImmutable('2026-10-21 12:01'), 'flow-a'));
        self::assertFalse($cursor->precedes(new \DateTimeImmutable('2026-10-21 11:59'), 'flow-z'));
        self::assertFalse($cursor->equals(SyncCursor::at(new \DateTimeImmutable('2026-10-21 12:00'), 'flow-c')));
    }

    public function testAMalformedPlatformCodeIsRefused(): void
    {
        self::assertTrue(PlatformCode::fromString('simulator')->equals(PlatformCode::fromString('simulator')));

        $this->expectException(\InvalidArgumentException::class);

        PlatformCode::fromString('Not a code');
    }

    public function testItIsReconstituted(): void
    {
        $connection = PlatformConnection::reconstitute(
            PlatformConnectionId::fromString('01960000-0000-7000-8000-0000000ec001'),
            Siren::fromInput('812456903'),
            PlatformCode::fromString('simulator'),
            OnboardingState::VERIFIED,
            null,
            SyncCursor::at(new \DateTimeImmutable('2026-10-21'), 'f'),
            new \DateTimeImmutable('2026-10-20'),
            new \DateTimeImmutable('2026-10-20'),
        );

        self::assertTrue($connection->isPolled());
        self::assertSame([], $connection->pullDomainEvents());
    }

    private function open(): PlatformConnection
    {
        return PlatformConnection::open(
            PlatformConnectionId::fromString('01960000-0000-7000-8000-0000000ec001'),
            Siren::fromInput('812456903'),
            PlatformCode::fromString('simulator'),
            new \DateTimeImmutable('2026-10-21 10:00'),
        );
    }
}
