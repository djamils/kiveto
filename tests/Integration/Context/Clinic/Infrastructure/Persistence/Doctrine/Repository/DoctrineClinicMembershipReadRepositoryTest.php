<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineClinicMembershipReadRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string USER_ID = '01912345-6789-7abc-8def-000000000001';

    public function testFindAccessibleClinicsForUserReturnsActiveMemberships(): void
    {
        $clinic = ClinicEntityFactory::createOne(['slug' => 'test-read-repo']);

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinic->getId()->toRfc4122())
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $result = $this->repo()->findAccessibleClinicsForUser(UserId::fromString(self::USER_ID));

        self::assertCount(1, $result);
        self::assertSame($clinic->getId()->toRfc4122(), $result[0]->clinicId);
        self::assertFalse($result[0]->isDefault);
    }

    public function testFindAccessibleClinicsReturnsIsDefaultField(): void
    {
        $clinic = ClinicEntityFactory::createOne(['slug' => 'test-read-default']);

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinic->getId()->toRfc4122())
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01'), 'isDefault' => true])
        ;

        $result = $this->repo()->findAccessibleClinicsForUser(UserId::fromString(self::USER_ID));

        self::assertCount(1, $result);
        self::assertTrue($result[0]->isDefault);
    }

    public function testReturnsEmptyForDisabledMembership(): void
    {
        $clinic = ClinicEntityFactory::createOne(['slug' => 'test-read-disabled']);

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinic->getId()->toRfc4122())
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asEmployee()
            ->disabled()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $result = $this->repo()->findAccessibleClinicsForUser(UserId::fromString(self::USER_ID));

        self::assertSame([], $result);
    }

    public function testReturnsEmptyForUnknownUser(): void
    {
        $result = $this->repo()->findAccessibleClinicsForUser(
            UserId::fromString('01912345-6789-7abc-8def-999999999999'),
        );

        self::assertSame([], $result);
    }

    public function testReturnsClinicWithNonNullValidUntil(): void
    {
        $clinic = ClinicEntityFactory::createOne(['slug' => 'test-read-validuntil']);

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinic->getId()->toRfc4122())
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asContractor(new \DateTimeImmutable('2027-01-01'))
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $result = $this->repo()->findAccessibleClinicsForUser(UserId::fromString(self::USER_ID));

        self::assertCount(1, $result);
        self::assertNotNull($result[0]->validUntil);
    }

    public function testFindVeterinariansForClinicReturnsOnlyActiveVeterinariesInOrder(): void
    {
        $clinic      = ClinicEntityFactory::createOne(['slug' => 'vet-list-clinic']);
        $clinicIdStr = $clinic->getId()->toRfc4122();

        $vet1 = '01912345-6789-7abc-8def-0000000000a1';
        $vet2 = '01912345-6789-7abc-8def-0000000000a2';
        $vet3 = '01912345-6789-7abc-8def-0000000000a3';

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinicIdStr)
            ->withUserId($vet1)
            ->asVeterinary()
            ->asEmployee()
            ->create([
                'validFrom' => new \DateTimeImmutable('2026-01-01'),
                'createdAt' => new \DateTimeImmutable('2026-01-01 10:00:00'),
            ])
        ;
        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinicIdStr)
            ->withUserId($vet2)
            ->asVeterinary()
            ->asEmployee()
            ->create([
                'validFrom' => new \DateTimeImmutable('2026-01-01'),
                'createdAt' => new \DateTimeImmutable('2026-01-02 10:00:00'),
            ])
        ;
        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinicIdStr)
            ->withUserId($vet3)
            ->asVeterinary()
            ->asEmployee()
            ->create([
                'validFrom' => new \DateTimeImmutable('2026-01-01'),
                'createdAt' => new \DateTimeImmutable('2026-01-03 10:00:00'),
            ])
        ;

        // Disabled vet — should be excluded
        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinicIdStr)
            ->withUserId('01912345-6789-7abc-8def-0000000000b1')
            ->asVeterinary()
            ->asEmployee()
            ->disabled()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;
        // Assistant — should be excluded
        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinicIdStr)
            ->withUserId('01912345-6789-7abc-8def-0000000000b2')
            ->asVeterinaryAssistant()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;
        // Manager — should be excluded
        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinicIdStr)
            ->withUserId('01912345-6789-7abc-8def-0000000000b3')
            ->asManager()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $result = $this->repo()->findVeterinariansForClinic(ClinicId::fromString($clinicIdStr));

        self::assertCount(3, $result);
        self::assertSame($vet1, $result[0]->userId);
        self::assertSame($vet2, $result[1]->userId);
        self::assertSame($vet3, $result[2]->userId);
        self::assertSame('VETERINARY', $result[0]->role);
    }

    public function testFindVeterinariansForClinicReturnsEmptyArrayWhenNoVeterinarian(): void
    {
        $clinic      = ClinicEntityFactory::createOne(['slug' => 'no-vets']);
        $clinicIdStr = $clinic->getId()->toRfc4122();

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinicIdStr)
            ->withUserId('01912345-6789-7abc-8def-0000000000c1')
            ->asVeterinaryAssistant()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $result = $this->repo()->findVeterinariansForClinic(ClinicId::fromString($clinicIdStr));

        self::assertSame([], $result);
    }

    public function testFindVeterinariansForClinicIsolatesClinicScope(): void
    {
        $clinicA = ClinicEntityFactory::createOne(['slug' => 'clinic-a']);
        $clinicB = ClinicEntityFactory::createOne(['slug' => 'clinic-b']);

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinicA->getId()->toRfc4122())
            ->withUserId('01912345-6789-7abc-8def-0000000000d1')
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;
        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinicB->getId()->toRfc4122())
            ->withUserId('01912345-6789-7abc-8def-0000000000d2')
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $resultA = $this->repo()->findVeterinariansForClinic(
            ClinicId::fromString($clinicA->getId()->toRfc4122()),
        );

        self::assertCount(1, $resultA);
        self::assertSame('01912345-6789-7abc-8def-0000000000d1', $resultA[0]->userId);
    }

    private function repo(): ClinicMembershipReadRepositoryInterface
    {
        $repo = self::getContainer()->get(ClinicMembershipReadRepositoryInterface::class);
        \assert($repo instanceof ClinicMembershipReadRepositoryInterface);

        return $repo;
    }
}
