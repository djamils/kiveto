<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Query\ListBookablePractitioners;

use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Application\Query\ListBookablePractitioners\BookablePractitioner;
use App\Context\Scheduling\Application\Query\ListBookablePractitioners\ListBookablePractitioners;
use App\Context\Scheduling\Application\Query\ListBookablePractitioners\ListBookablePractitionersHandler;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Scheduling\Support\FakeClinicDirectory;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryBookingMotifs;
use PHPUnit\Framework\TestCase;

final class ListBookablePractitionersHandlerTest extends TestCase
{
    private const string CLINIC   = '01960000-0000-7000-8000-00000000c001';
    private const string OTHER    = '01960000-0000-7000-8000-00000000c002';
    private const string MOTIF    = '01960000-0000-7000-8000-00000000b001';
    private const string OFFLINE  = '01960000-0000-7000-8000-00000000b002';
    private const string ARCHIVED = '01960000-0000-7000-8000-00000000b003';
    private const string NEW      = '01960000-0000-7000-8000-00000000b004';
    private const string VET_A    = '01960000-0000-7000-8000-00000000d001';
    private const string VET_B    = '01960000-0000-7000-8000-00000000d002';
    private const string ASV      = '01960000-0000-7000-8000-00000000d003';
    private const string FORMER   = '01960000-0000-7000-8000-00000000d004';

    /** @var list<array{string, string, string, array<mixed>}> */
    private array $eligibilityCalls = [];

    public function testTheMotifsStillEligiblePractitionersByName(): void
    {
        $practitioners = $this->handler()(new ListBookablePractitioners(self::CLINIC, self::MOTIF));

        self::assertNotNull($practitioners);
        self::assertSame(
            [[self::VET_B, 'Dr Alice Martin'], [self::VET_A, 'Dr Claire Rousseau'], [self::ASV, null]],
            array_map(static fn (BookablePractitioner $p): array => [$p->id, $p->displayName], $practitioners),
        );
        self::assertContains([self::FORMER, self::CLINIC, '2026-11-02 09:00', ['VETERINARY', 'VETERINARY_ASSISTANT']], $this->eligibilityCalls);
    }

    public function testAMotifNotBookableOnlineHasNoPractitionerList(): void
    {
        $handler = $this->handler();

        self::assertNull($handler(new ListBookablePractitioners(self::CLINIC, self::OFFLINE)), 'not open online');
        self::assertNull($handler(new ListBookablePractitioners(self::CLINIC, self::ARCHIVED)), 'archived');
        self::assertNull($handler(new ListBookablePractitioners(self::OTHER, self::MOTIF)), 'another clinic');
        self::assertNull($handler(new ListBookablePractitioners(self::CLINIC, 'not-a-uuid')));
    }

    public function testANewClientOnlyBooksTheMotifsOpenToNewClients(): void
    {
        $handler = $this->handler();

        self::assertNull($handler(new ListBookablePractitioners(self::CLINIC, self::MOTIF, newClient: true)));
        self::assertNotNull($handler(new ListBookablePractitioners(self::CLINIC, self::MOTIF)), 'a known client still books it');
        self::assertSame(
            [[self::VET_A, 'Dr Claire Rousseau']],
            array_map(static fn (BookablePractitioner $p): array => [$p->id, $p->displayName], $handler(new ListBookablePractitioners(self::CLINIC, self::NEW, newClient: true)) ?? []),
        );
    }

    private function handler(): ListBookablePractitionersHandler
    {
        $motifs = new InMemoryBookingMotifs();
        $motifs->save($this->motif(self::MOTIF, true, [self::VET_A, self::FORMER, self::ASV, self::VET_B]));
        $motifs->save($this->motif(self::OFFLINE, false, [self::VET_A]));
        $motifs->save($this->motif(self::NEW, true, [self::VET_A], newClients: true));
        $archived = $this->motif(self::ARCHIVED, true, [self::VET_A]);
        $archived->archive(new \DateTimeImmutable('2026-10-01 08:00:00'));
        $motifs->save($archived);

        $eligibility = $this->createStub(MembershipEligibilityCheckerInterface::class);
        $eligibility->method('isUserEligibleForClinicAt')->willReturnCallback(
            function (UserId $user, ClinicId $clinic, \DateTimeImmutable $at, array $roles): bool {
                /* @var list<string> $roles */
                $this->eligibilityCalls[] = [$user->toString(), $clinic->toString(), $at->format('Y-m-d H:i'), $roles];

                return self::FORMER !== $user->toString();
            },
        );

        return new ListBookablePractitionersHandler(
            $motifs,
            $eligibility,
            new FakeClinicDirectory(printedNames: [self::VET_A => 'Dr Claire Rousseau', self::VET_B => 'Dr Alice Martin', self::FORMER => 'Dr Paul Ancien']),
            new FrozenClock(new \DateTimeImmutable('2026-11-02 09:00:00', new \DateTimeZone('UTC'))),
        );
    }

    /**
     * @param list<string> $practitioners
     */
    private function motif(string $id, bool $online, array $practitioners, bool $newClients = false): BookingMotif
    {
        return BookingMotif::create(
            BookingMotifId::fromString($id),
            ClinicId::fromString(self::CLINIC),
            'Consultation',
            null,
            30,
            $online,
            ConfirmationMode::AUTO,
            [PlanningBlockType::CONSULTATION],
            array_map(UserId::fromString(...), $practitioners),
            null,
            $newClients,
            0,
        );
    }
}
