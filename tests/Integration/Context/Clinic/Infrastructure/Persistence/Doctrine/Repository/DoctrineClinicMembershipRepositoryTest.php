<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineClinicMembershipRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01912345-6789-7abc-8def-000000000001';
    private const string USER_ID   = '01912345-6789-7abc-8def-000000000002';

    public function testSaveAndFindById(): void
    {
        $membership = ClinicMembership::create(
            id: ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000099'),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            userId: UserId::fromString(self::USER_ID),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2026-01-01 00:00:00'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
            isClinicAdministrator: false,
        );

        $this->repo()->save($membership);

        $found = $this->repo()->findById(ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000099'));

        self::assertNotNull($found);
        self::assertSame('01912345-6789-7abc-8def-000000000099', $found->id()->toString());
        self::assertSame(ClinicMemberRole::VETERINARY, $found->role());
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $found->engagement());
        self::assertSame(ClinicMembershipStatus::ACTIVE, $found->status());
        self::assertFalse($found->isDefault());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $id = ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000099');

        self::assertNull($this->repo()->findById($id));
    }

    public function testFindByClinicAndUser(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $found = $this->repo()->findByClinicAndUser(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
        );

        self::assertNotNull($found);
        self::assertSame(self::CLINIC_ID, $found->clinicId()->toString());
        self::assertSame(self::USER_ID, $found->userId()->toString());
    }

    public function testFindByClinicAndUserReturnsNull(): void
    {
        self::assertNull($this->repo()->findByClinicAndUser(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
        ));
    }

    public function testExistsByClinicAndUser(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        self::assertTrue($this->repo()->existsByClinicAndUser(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
        ));
    }

    public function testExistsByClinicAndUserReturnsFalse(): void
    {
        self::assertFalse($this->repo()->existsByClinicAndUser(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
        ));
    }

    public function testFindDefaultForUserReturnsActiveDefaultMembership(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asEmployee()
            ->asDefault()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $found = $this->repo()->findDefaultForUser(UserId::fromString(self::USER_ID));

        self::assertNotNull($found);
        self::assertTrue($found->isDefault());
        self::assertSame(self::USER_ID, $found->userId()->toString());
    }

    public function testFindDefaultForUserReturnsNullWhenNoDefault(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        self::assertNull($this->repo()->findDefaultForUser(UserId::fromString(self::USER_ID)));
    }

    public function testFindDefaultForUserIgnoresDisabledMembership(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asEmployee()
            ->asDefault()
            ->disabled()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        self::assertNull($this->repo()->findDefaultForUser(UserId::fromString(self::USER_ID)));
    }

    public function testSaveUpdatesExistingEntity(): void
    {
        $proxy = ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $id = $proxy->getId()->toRfc4122();

        $membership = $this->repo()->findById(ClinicMembershipId::fromString($id));
        self::assertNotNull($membership);

        $membership->changeRole(ClinicMemberRole::MANAGER);
        $this->repo()->save($membership);

        $updated = $this->repo()->findById(ClinicMembershipId::fromString($id));
        self::assertNotNull($updated);
        self::assertSame(ClinicMemberRole::MANAGER, $updated->role());
    }

    /**
     * The update branch enumerates its setters by hand, in save() and again in
     * saveAll(). Miss either and the hat persists on insert but never on edit —
     * which nothing in this batch would notice, because nothing edits it yet.
     */
    public function testSavePersistsTheAdministrationHatOnUpdate(): void
    {
        $id = $this->persistedMembershipWithoutTheHat('save-hat-test');

        $this->repo()->save($this->sameMembershipWearingTheHat($id));

        $reloaded = $this->repo()->findById(ClinicMembershipId::fromString($id));
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isClinicAdministrator());
    }

    public function testSaveAllPersistsTheAdministrationHatOnUpdate(): void
    {
        $id = $this->persistedMembershipWithoutTheHat('save-all-hat-test');

        $this->repo()->saveAll($this->sameMembershipWearingTheHat($id));

        $reloaded = $this->repo()->findById(ClinicMembershipId::fromString($id));
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isClinicAdministrator());
    }

    public function testSavePersistsTheAdministrationHatOnInsert(): void
    {
        $membership = ClinicMembership::create(
            id: ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000098'),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            userId: UserId::fromString(self::USER_ID),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2026-01-01 00:00:00'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
            isClinicAdministrator: true,
        );

        $this->repo()->save($membership);

        $found = $this->repo()->findById(ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000098'));
        self::assertNotNull($found);
        self::assertTrue($found->isClinicAdministrator());
    }

    /**
     * "Effective" must mean the same thing here as in isEffectiveAt(): status
     * AND window. A status-only filter passes every case below except the
     * expired one, which is exactly the one that matters.
     */
    public function testCountEffectiveAdministratorsIgnoresIneffectiveOnes(): void
    {
        $clinicId = '01912345-6789-7abc-8def-0000000000c1';
        $other    = '01912345-6789-7abc-8def-0000000000c2';
        $now      = new \DateTimeImmutable('2026-06-01 10:00:00');

        $this->administrator($clinicId, 'a1');
        $this->administrator($clinicId, 'a2', status: ClinicMembershipStatus::DISABLED);
        $this->administrator($clinicId, 'a3', validUntil: new \DateTimeImmutable('2026-03-01'));
        $this->administrator($clinicId, 'a4', validFrom: new \DateTimeImmutable('2026-09-01'));
        $this->administrator($other, 'a5');

        // A member of the same clinic without the hat must not count either.
        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinicId)
            ->withUserId('01912345-6789-7abc-8def-0000000000f9')
            ->asReceptionist()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        self::assertSame(1, $this->repo()->countEffectiveAdministrators(
            ClinicId::fromString($clinicId),
            $now,
        ));
    }

    public function testCountEffectiveAdministratorsHonoursExcluding(): void
    {
        $clinicId = '01912345-6789-7abc-8def-0000000000d1';
        $now      = new \DateTimeImmutable('2026-06-01 10:00:00');

        $first  = $this->administrator($clinicId, 'b1');
        $second = $this->administrator($clinicId, 'b2');

        self::assertSame(2, $this->repo()->countEffectiveAdministrators(
            ClinicId::fromString($clinicId),
            $now,
        ));

        self::assertSame(1, $this->repo()->countEffectiveAdministrators(
            ClinicId::fromString($clinicId),
            $now,
            ClinicMembershipId::fromString($first),
        ));

        self::assertSame(1, $this->repo()->countEffectiveAdministrators(
            ClinicId::fromString($clinicId),
            $now,
            ClinicMembershipId::fromString($second),
        ));
    }

    public function testCountEffectiveAdministratorsReturnsZeroForAnEmptyClinic(): void
    {
        self::assertSame(0, $this->repo()->countEffectiveAdministrators(
            ClinicId::fromString('01912345-6789-7abc-8def-0000000000e1'),
            new \DateTimeImmutable('2026-06-01 10:00:00'),
        ));
    }

    /**
     * countEffectiveAdministrators() restates isEffectiveAt()'s predicate in
     * DQL, two layers apart. Bind them: whatever the domain calls an effective
     * administrator is exactly what the query counts. Change one without the
     * other and this goes red.
     */
    public function testCountAgreesWithTheDomainPredicate(): void
    {
        $clinicId = '01912345-6789-7abc-8def-0000000000f1';
        $now      = new \DateTimeImmutable('2026-06-01 10:00:00');

        $ids = [
            $this->administrator($clinicId, 'g1'),
            $this->administrator($clinicId, 'g2', status: ClinicMembershipStatus::DISABLED),
            $this->administrator($clinicId, 'g3', validUntil: new \DateTimeImmutable('2026-03-01')),
            $this->administrator($clinicId, 'g4', validFrom: new \DateTimeImmutable('2026-09-01')),
            $this->administrator($clinicId, 'g5', validUntil: new \DateTimeImmutable('2026-12-31')),
            $this->persistedMembershipWithoutTheHat('g6'),
        ];

        $expected = 0;
        foreach ($ids as $id) {
            $membership = $this->repo()->findById(ClinicMembershipId::fromString($id));
            self::assertNotNull($membership);

            if ($membership->clinicId()->toString() === $clinicId && $membership->isEffectiveAdministratorAt($now)) {
                ++$expected;
            }
        }

        self::assertSame($expected, $this->repo()->countEffectiveAdministrators(
            ClinicId::fromString($clinicId),
            $now,
        ));
    }

    private function administrator(
        string $clinicId,
        string $userSeed,
        ClinicMembershipStatus $status = ClinicMembershipStatus::ACTIVE,
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validUntil = null,
    ): string {
        $proxy = ClinicMembershipEntityFactory::new()
            ->withClinicId($clinicId)
            ->withUserId(\sprintf('01912345-6789-7abc-8def-%012d', crc32($userSeed) % 1000000))
            ->asReceptionist()
            ->asEmployee()
            ->asClinicAdministrator()
            ->create([
                'status'     => $status,
                'validFrom'  => $validFrom ?? new \DateTimeImmutable('2026-01-01'),
                'validUntil' => $validUntil,
            ])
        ;

        return $proxy->getId()->toRfc4122();
    }

    private function persistedMembershipWithoutTheHat(string $userSeed): string
    {
        $proxy = ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(\sprintf('01912345-6789-7abc-8def-%012d', crc32($userSeed) % 1000000))
            ->asReceptionist()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        self::assertFalse($proxy->getIsClinicAdministrator());

        return $proxy->getId()->toRfc4122();
    }

    /**
     * Batch 1 ships no mutator for the hat, so the update path is exercised by
     * saving a reconstituted aggregate that wears it over a row that does not.
     */
    private function sameMembershipWearingTheHat(string $id): ClinicMembership
    {
        $existing = $this->repo()->findById(ClinicMembershipId::fromString($id));
        self::assertNotNull($existing);

        return ClinicMembership::reconstitute(
            id: $existing->id(),
            clinicId: $existing->clinicId(),
            userId: $existing->userId(),
            role: $existing->role(),
            engagement: $existing->engagement(),
            status: $existing->status(),
            validFrom: $existing->validFrom(),
            validUntil: $existing->validUntil(),
            createdAt: $existing->createdAt(),
            isDefault: $existing->isDefault(),
            isClinicAdministrator: true,
        );
    }

    private function repo(): ClinicMembershipRepositoryInterface
    {
        $repo = self::getContainer()->get(ClinicMembershipRepositoryInterface::class);
        \assert($repo instanceof ClinicMembershipRepositoryInterface);

        return $repo;
    }
}
