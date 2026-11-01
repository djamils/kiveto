<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\SaveBookingMotif;

use App\Context\Scheduling\Application\Command\SaveBookingMotif\SaveBookingMotif;
use App\Context\Scheduling\Application\Command\SaveBookingMotif\SaveBookingMotifHandler;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Domain\Booking\BookingMotifNotFoundException;
use App\Context\Scheduling\Domain\Booking\BookingPreventiveCareKind;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\InvalidBookingConfigurationException;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryBookingMotifs;
use PHPUnit\Framework\TestCase;

final class SaveBookingMotifHandlerTest extends TestCase
{
    private const string NEW_ID = '01960000-0000-7000-8000-00000000b001';
    private const string CLINIC = '01960000-0000-7000-8000-00000000c001';
    private const string OTHER  = '01960000-0000-7000-8000-00000000c002';
    private const string VET_A  = '01960000-0000-7000-8000-00000000d001';
    private const string VET_B  = '01960000-0000-7000-8000-00000000d002';
    private const string FORMER = '01960000-0000-7000-8000-00000000d003';

    private InMemoryBookingMotifs $motifs;

    /** @var list<array{string, string, string, array<mixed>}> */
    private array $eligibilityCalls = [];

    protected function setUp(): void
    {
        $this->motifs = new InMemoryBookingMotifs();
    }

    public function testCreatesAMotifAndReturnsItsId(): void
    {
        $id = $this->handler()($this->command());

        self::assertSame(self::NEW_ID, $id);
        $motif = $this->motifs->motifs[self::NEW_ID];
        self::assertSame(self::CLINIC, $motif->clinicId()->toString());
        self::assertSame('Consultation', $motif->label());
        self::assertSame('Première visite', $motif->description());
        self::assertSame(30, $motif->durationMin());
        self::assertTrue($motif->isOnlineEnabled());
        self::assertSame(ConfirmationMode::MANUAL, $motif->confirmationKnownClient());
        self::assertSame([PlanningBlockType::CONSULTATION, PlanningBlockType::HEALTH_CHECK], $motif->allowedBlockTypes());
        self::assertSame([self::VET_A, self::VET_B], array_map(static fn (UserId $id): string => $id->toString(), $motif->practitionerIds()));
        self::assertSame(BookingPreventiveCareKind::VACCINATION, $motif->preventiveCareKind());
        self::assertTrue($motif->newClientsAllowed());
        self::assertSame(2, $motif->sortOrder());
        self::assertSame([
            [self::VET_A, self::CLINIC, '2026-11-01 09:00', ['VETERINARY', 'VETERINARY_ASSISTANT']],
            [self::VET_B, self::CLINIC, '2026-11-01 09:00', ['VETERINARY', 'VETERINARY_ASSISTANT']],
        ], $this->eligibilityCalls);
    }

    public function testUpdatesAMotifOfTheClinic(): void
    {
        $id = $this->handler()($this->command());

        $returned = $this->handler()($this->command(motifId: $id, label: 'Vaccin', preventiveCareKind: '', confirmation: 'auto'));

        self::assertSame($id, $returned);
        self::assertCount(1, $this->motifs->motifs);
        self::assertSame('Vaccin', $this->motifs->motifs[$id]->label());
        self::assertNull($this->motifs->motifs[$id]->preventiveCareKind());
        self::assertSame(ConfirmationMode::AUTO, $this->motifs->motifs[$id]->confirmationKnownClient());
    }

    public function testAMotifOfAnotherClinicIsNotFound(): void
    {
        $id = $this->handler()($this->command());

        $this->expectException(BookingMotifNotFoundException::class);
        $this->expectExceptionMessage(\sprintf('Booking motif "%s" not found.', $id));

        $this->handler()($this->command(clinicId: self::OTHER, motifId: $id));
    }

    public function testAPractitionerNotEligibleAtTheClinicIsRefused(): void
    {
        $this->assertRefused($this->command(practitionerIds: [self::VET_A, self::FORMER]), 'practitionerIds', 'Ce praticien ne peut pas prendre de rendez-vous dans cette clinique.');
    }

    public function testUnknownValuesAreRefused(): void
    {
        $this->assertRefused($this->command(confirmation: 'sometimes'), 'confirmationKnownClient', 'Mode de confirmation inconnu.');
        $this->assertRefused($this->command(preventiveCareKind: 'grooming'), 'preventiveCareKind', 'Type de soin préventif inconnu.');
        $this->assertRefused($this->command(blockTypes: ['consultation', 'nap']), 'allowedBlockTypes', 'Type de plage inconnu.');
    }

    public function testDomainBoundsApply(): void
    {
        $this->assertRefused($this->command(blockTypes: ['leave']), 'allowedBlockTypes', 'Ce type de plage n\'accepte pas de rendez-vous.');
        self::assertSame(0, $this->motifs->saves);
    }

    private function assertRefused(SaveBookingMotif $command, string $field, string $message): void
    {
        try {
            $this->handler()($command);
            self::fail('The motif should be refused.');
        } catch (InvalidBookingConfigurationException $e) {
            self::assertSame($field, $e->field);
            self::assertSame($message, $e->getMessage());
        }
    }

    private function handler(): SaveBookingMotifHandler
    {
        $eligibility = $this->createStub(MembershipEligibilityCheckerInterface::class);
        $eligibility->method('isUserEligibleForClinicAt')->willReturnCallback(
            function (UserId $user, ClinicId $clinic, \DateTimeImmutable $at, array $roles): bool {
                /* @var list<string> $roles */
                $this->eligibilityCalls[] = [$user->toString(), $clinic->toString(), $at->format('Y-m-d H:i'), $roles];

                return self::FORMER !== $user->toString();
            },
        );

        $uuids = $this->createStub(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::NEW_ID);

        return new SaveBookingMotifHandler(
            $this->motifs,
            $eligibility,
            $uuids,
            new FrozenClock(new \DateTimeImmutable('2026-11-01 09:00:00')),
        );
    }

    /**
     * @param list<string> $blockTypes
     * @param list<string> $practitionerIds
     */
    private function command(
        string $clinicId = self::CLINIC,
        ?string $motifId = null,
        string $label = 'Consultation',
        string $confirmation = 'manual',
        array $blockTypes = ['consultation', 'health_check'],
        array $practitionerIds = [self::VET_A, self::VET_B],
        ?string $preventiveCareKind = 'vaccination',
    ): SaveBookingMotif {
        return new SaveBookingMotif(
            clinicId: $clinicId,
            motifId: $motifId,
            label: $label,
            description: 'Première visite',
            durationMin: 30,
            onlineEnabled: true,
            confirmationKnownClient: $confirmation,
            allowedBlockTypes: $blockTypes,
            practitionerIds: $practitionerIds,
            preventiveCareKind: $preventiveCareKind,
            newClientsAllowed: true,
            sortOrder: 2,
        );
    }
}
