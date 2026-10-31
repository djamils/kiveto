<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Animal\Domain\ValueObject\LifeStatus;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Clinic\Factory\StaffProfileEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 3: the home of an owner in a clinic (AC 19, 24, 27, 111, 113).
 * Today is 2 November 2026, 10:00 UTC (frozen clock).
 */
final class HomeApiTest extends PetsApiTestCase
{
    private const string THIRD_CLIENT = '01930000-0000-7000-8000-00000000d303';

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.83';
        parent::setUp();
    }

    public function testAnEmptyHomeHasEmptySectionsAndNoNextAppointment(): void
    {
        self::assertSame(
            ['nextAppointment' => null, 'pets' => [], 'careItems' => [], 'latestDocuments' => []],
            self::json($this->authGet('/clinics/' . self::CLINIC . '/home'), 200),
        );
    }

    public function testTheHomeGathersAlivePetsTheirNearestCareTheNextAppointmentAndTheLatestDocuments(): void
    {
        $this->link(self::OTHER_CLIENT);
        $rex    = $this->pet('Rex');
        $nala   = $this->pet('Nala', self::OTHER_CLIENT);
        $ghost  = $this->pet('Anubis', attributes: ['lifeStatus' => LifeStatus::DECEASED, 'deceasedAt' => new \DateTimeImmutable('2026-06-01')]);
        $lost   = $this->pet('Bandit', attributes: ['lifeStatus' => LifeStatus::MISSING, 'missingSince' => new \DateTimeImmutable('2026-08-01')]);
        $shared = $this->pet('Copropriété', primary: false);

        $this->careAct($rex, PreventiveCareKind::VACCINATION, 'CHPPiL', '2025-11-20', '2026-11-20');
        $this->careAct($rex, PreventiveCareKind::INTERNAL_PARASITE, 'Milbemax', '2026-08-01', '2026-11-01');
        $this->careAct($nala, PreventiveCareKind::VACCINATION, 'Typhus', '2025-11-10', '2026-11-10');
        $this->careAct($nala, PreventiveCareKind::EXTERNAL_PARASITE, 'Bravecto', '2026-10-01', '2027-01-01');
        $this->careAct($ghost, PreventiveCareKind::VACCINATION, 'Rage', '2025-01-01', '2026-01-01');
        $this->careAct($lost, PreventiveCareKind::VACCINATION, 'Rage', '2025-01-01', '2026-01-01');

        $vet = $this->veterinarian('Dr Claire Lambert');
        $this->appointment($ghost, '2026-11-03 08:00:00', 'Rappel vaccin');
        $this->appointment($shared, '2026-11-03 09:00:00', 'Rappel vaccin');
        $this->appointment(null, '2026-11-03 10:00:00', 'Rappel vaccin');
        $this->appointment($rex, '2026-11-01 08:00:00', 'Rappel vaccin');
        $this->appointment($rex, '2026-11-03 11:00:00', 'Rappel vaccin', AppointmentStatus::CANCELLED);
        $next = $this->appointment($nala, '2026-11-04 13:30:00', 'Rappel vaccin CHPPiL — apporter le carnet', practitioner: $vet);
        $this->appointment($rex, '2026-11-05 08:00:00', 'Contrôle');

        $this->report(self::CLIENT, '2026-10-01 10:00:00', [$rex]);
        $latest = $this->report(self::CLIENT, '2026-10-03 10:00:00', [$rex]);
        $middle = $this->report(self::OTHER_CLIENT, '2026-10-02 10:00:00', [$nala]);
        $this->report(self::THIRD_CLIENT, '2026-10-04 10:00:00', []);

        $home = self::json($this->authGet('/clinics/' . self::CLINIC . '/home'), 200);

        self::assertSame([
            'id'           => $next,
            'startAt'      => '2026-11-04T14:30:00+01:00',
            'pet'          => ['id' => $nala, 'name' => 'Nala'],
            'practitioner' => 'Dr Claire Lambert',
            'motifLabel'   => 'Vaccination',
        ], $home['nextAppointment']);
        self::assertSame([$nala, $rex], array_column(self::rows($home['pets']), 'id'));
        self::assertSame(['id', 'name', 'species', 'breed', 'sex', 'sterilization', 'birthDate', 'initial', 'lifeStatus'], array_keys(self::rows($home['pets'])[0]));
        self::assertSame([
            ['pet' => ['id' => $rex, 'name' => 'Rex'], 'kind' => 'internal_parasite', 'label' => 'Milbemax', 'lastDoneOn' => '2026-08-01', 'dueOn' => '2026-11-01', 'status' => 'overdue'],
            ['pet' => ['id' => $nala, 'name' => 'Nala'], 'kind' => 'vaccination', 'label' => 'Typhus', 'lastDoneOn' => '2025-11-10', 'dueOn' => '2026-11-10', 'status' => 'due_soon'],
            ['pet' => ['id' => $rex, 'name' => 'Rex'], 'kind' => 'vaccination', 'label' => 'CHPPiL', 'lastDoneOn' => '2025-11-20', 'dueOn' => '2026-11-20', 'status' => 'due_soon'],
        ], $home['careItems']);
        self::assertSame([$latest, $middle], array_column(self::rows($home['latestDocuments']), 'id'));
        self::assertSame('2026-10-03T12:00:00+02:00', self::rows($home['latestDocuments'])[0]['issuedAt']);
    }

    public function testOnlyAPetlessOrInvisibleAppointmentLeavesNoNextAppointment(): void
    {
        $rex    = $this->pet('Rex');
        $shared = $this->pet('Copropriété', primary: false);
        $this->appointment($shared, '2026-11-03 09:00:00', 'Chirurgie');
        $this->appointment(null, '2026-11-03 10:00:00', 'Consultation');
        $this->appointment($rex, '2026-11-01 10:00:00', 'Consultation');

        $home = self::json($this->authGet('/clinics/' . self::CLINIC . '/home'), 200);
        self::assertNull($home['nextAppointment']);
        self::assertSame([$rex], array_column(self::rows($home['pets']), 'id'));

        $this->appointment($rex, '2026-11-06 10:00:00', 'Stérilisation');
        $home = self::json($this->authGet('/clinics/' . self::CLINIC . '/home'), 200);
        self::assertIsArray($home['nextAppointment']);
        self::assertNull($home['nextAppointment']['practitioner']);
        self::assertSame('Chirurgie', $home['nextAppointment']['motifLabel']);
    }

    public function testMeGivesEachClinicItsTimezone(): void
    {
        $clinics = self::rows(self::json($this->authGet('/me'), 200)['clinics']);

        self::assertSame('Europe/Paris', $clinics[0]['timezone']);
    }

    protected function phone(): string
    {
        return '+33615000303';
    }

    private function appointment(?string $petId, string $startsAtUtc, string $reason, AppointmentStatus $status = AppointmentStatus::PLANNED, ?string $practitioner = null): string
    {
        $id      = Uuid::v7()->toRfc4122();
        $factory = AppointmentEntityFactory::new()->withId($id)->withClinicId(self::CLINIC)
            ->startingAt(new \DateTimeImmutable($startsAtUtc, new \DateTimeZone('UTC')))
            ->withStatus($status)
            ->afterInstantiate(static function (AppointmentEntity $appointment) use ($petId, $reason): void {
                $appointment->setAnimalId(null === $petId ? null : Uuid::fromString($petId));
                $appointment->setOwnerId(Uuid::fromString(self::CLIENT));
                $appointment->setReason($reason);
            })
        ;

        (null === $practitioner ? $factory : $factory->withPractitionerUserId($practitioner))->create();

        return $id;
    }

    /** A veterinarian of the clinic with an agenda display name; returns their user id. */
    private function veterinarian(string $displayName): string
    {
        $user       = ClinicUserFactory::createOne();
        $userId     = $user->getId()->toRfc4122();
        $membership = Uuid::v7()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withId($membership)->withClinicId(self::CLINIC)->withUserId($userId)->withRole(ClinicMemberRole::VETERINARY)->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;
        StaffProfileEntityFactory::new()->forMembership(Uuid::fromString($membership))->create(['displayName' => $displayName]);

        return $userId;
    }
}
