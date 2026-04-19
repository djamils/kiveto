<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineStaffProfileReadRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testBatchQueryReturnsCorrectMapForExistingProfiles(): void
    {
        $m1 = ClinicMembershipEntityFactory::new()->asVeterinary()->create();
        $m2 = ClinicMembershipEntityFactory::new()->asVeterinary()->create();

        $id1 = $m1->getId()->toRfc4122();
        $id2 = $m2->getId()->toRfc4122();

        $map = $this->readRepo()->findByMembershipIds([$id1, $id2]);

        self::assertCount(2, $map);
        self::assertArrayHasKey($id1, $map);
        self::assertArrayHasKey($id2, $map);
        self::assertSame($id1, $map[$id1]->membershipId);
        self::assertSame($id2, $map[$id2]->membershipId);
    }

    public function testEmptyInputReturnsEmptyMap(): void
    {
        $map = $this->readRepo()->findByMembershipIds([]);

        self::assertSame([], $map);
    }

    public function testMissingMembershipIsAbsentFromMap(): void
    {
        $nonExistentId = '01912345-6789-7abc-8def-aaaaaaaaaaaa';

        $map = $this->readRepo()->findByMembershipIds([$nonExistentId]);

        self::assertSame([], $map);
    }

    public function testHasVeterinaryCredentialsForReturnsTrueWhenCredentialsExist(): void
    {
        $membership   = ClinicMembershipEntityFactory::new()->asVeterinary()->create();
        $membershipId = ClinicMembershipId::fromString($membership->getId()->toRfc4122());

        // asVeterinary afterPersist creates a profile with credentials (asDoctor)
        $hasCredentials = $this->readRepo()->hasVeterinaryCredentialsFor($membershipId);

        self::assertTrue($hasCredentials);
    }

    public function testHasVeterinaryCredentialsForReturnsFalseWhenNoProfile(): void
    {
        $membership   = ClinicMembershipEntityFactory::new()->asManager()->create();
        $membershipId = ClinicMembershipId::fromString($membership->getId()->toRfc4122());

        $hasCredentials = $this->readRepo()->hasVeterinaryCredentialsFor($membershipId);

        self::assertFalse($hasCredentials);
    }

    private function readRepo(): StaffProfileReadRepositoryInterface
    {
        $repo = self::getContainer()->get(StaffProfileReadRepositoryInterface::class);
        \assert($repo instanceof StaffProfileReadRepositoryInterface);

        return $repo;
    }
}
