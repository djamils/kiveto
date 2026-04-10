<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Application\Port\MembershipAdminRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineMembershipAdminRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testListAllReturnsCollection(): void
    {
        $clinic = ClinicEntityFactory::createOne(['slug' => 'admin-repo-test']);
        $user   = ClinicUserFactory::createOne();

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinic->getId()->toRfc4122())
            ->withUserId($user->getId()->toRfc4122())
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $collection = $this->repo()->listAll();

        self::assertSame(1, $collection->total);
        self::assertCount(1, $collection->memberships);
        self::assertSame($clinic->getId()->toRfc4122(), $collection->memberships[0]->clinicId);
        self::assertSame($user->getEmail(), $collection->memberships[0]->userEmail);
    }

    public function testListAllWithRoleFilter(): void
    {
        $clinic = ClinicEntityFactory::createOne(['slug' => 'admin-filter-test']);
        $user1  = ClinicUserFactory::new()->withEmail('vet-filter@kiveto.local')->create();
        $user2  = ClinicUserFactory::new()->withEmail('mgr-filter@kiveto.local')->create();

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinic->getId()->toRfc4122())
            ->withUserId($user1->getId()->toRfc4122())
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinic->getId()->toRfc4122())
            ->withUserId($user2->getId()->toRfc4122())
            ->asManager()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $collection = $this->repo()->listAll(role: ClinicMemberRole::VETERINARY);

        self::assertSame(1, $collection->total);
        self::assertSame(ClinicMemberRole::VETERINARY, $collection->memberships[0]->role);
    }

    public function testListAllWithStatusFilter(): void
    {
        $clinic = ClinicEntityFactory::createOne(['slug' => 'admin-status-test']);
        $user   = ClinicUserFactory::new()->withEmail('disabled-filter@kiveto.local')->create();

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinic->getId()->toRfc4122())
            ->withUserId($user->getId()->toRfc4122())
            ->asVeterinary()
            ->asEmployee()
            ->disabled()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $active   = $this->repo()->listAll(status: ClinicMembershipStatus::ACTIVE);
        $disabled = $this->repo()->listAll(status: ClinicMembershipStatus::DISABLED);

        self::assertSame(0, $active->total);
        self::assertSame(1, $disabled->total);
    }

    public function testListAllReturnsEmptyWhenNoData(): void
    {
        $collection = $this->repo()->listAll();

        self::assertSame(0, $collection->total);
        self::assertSame([], $collection->memberships);
    }

    public function testListAllWithClinicIdFilter(): void
    {
        $clinic1 = ClinicEntityFactory::createOne(['slug' => 'admin-clinic1']);
        $clinic2 = ClinicEntityFactory::createOne(['slug' => 'admin-clinic2']);
        $user    = ClinicUserFactory::new()->withEmail('clinicfilter@kiveto.local')->create();

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinic1->getId()->toRfc4122())
            ->withUserId($user->getId()->toRfc4122())
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinic2->getId()->toRfc4122())
            ->withUserId($user->getId()->toRfc4122())
            ->asManager()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $collection = $this->repo()->listAll(clinicId: $clinic1->getId()->toRfc4122());

        self::assertSame(1, $collection->total);
        self::assertSame($clinic1->getId()->toRfc4122(), $collection->memberships[0]->clinicId);
    }

    public function testListAllWithUserIdFilter(): void
    {
        $clinic = ClinicEntityFactory::createOne(['slug' => 'admin-userfilter']);
        $user1  = ClinicUserFactory::new()->withEmail('user1filter@kiveto.local')->create();
        $user2  = ClinicUserFactory::new()->withEmail('user2filter@kiveto.local')->create();

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinic->getId()->toRfc4122())
            ->withUserId($user1->getId()->toRfc4122())
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinic->getId()->toRfc4122())
            ->withUserId($user2->getId()->toRfc4122())
            ->asManager()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $collection = $this->repo()->listAll(userId: $user1->getId()->toRfc4122());

        self::assertSame(1, $collection->total);
        self::assertSame($user1->getId()->toRfc4122(), $collection->memberships[0]->userId);
    }

    public function testListAllWithEngagementFilter(): void
    {
        $clinic = ClinicEntityFactory::createOne(['slug' => 'admin-engfilter']);
        $user   = ClinicUserFactory::new()->withEmail('engfilter@kiveto.local')->create();

        ClinicMembershipEntityFactory::new()
            ->withClinicId($clinic->getId()->toRfc4122())
            ->withUserId($user->getId()->toRfc4122())
            ->asVeterinary()
            ->asContractor(new \DateTimeImmutable('2027-01-01'))
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $employees   = $this->repo()->listAll(engagement: ClinicMembershipEngagement::EMPLOYEE);
        $contractors = $this->repo()->listAll(engagement: ClinicMembershipEngagement::CONTRACTOR);

        self::assertSame(0, $employees->total);
        self::assertSame(1, $contractors->total);
        self::assertNotNull($contractors->memberships[0]->validUntil);
    }

    private function repo(): MembershipAdminRepositoryInterface
    {
        $repo = self::getContainer()->get(MembershipAdminRepositoryInterface::class);
        \assert($repo instanceof MembershipAdminRepositoryInterface);

        return $repo;
    }
}
