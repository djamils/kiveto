<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
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

    private function repo(): ClinicMembershipReadRepositoryInterface
    {
        $repo = self::getContainer()->get(ClinicMembershipReadRepositoryInterface::class);
        \assert($repo instanceof ClinicMembershipReadRepositoryInterface);

        return $repo;
    }
}
