<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Application\Command\Staff\OnboardStaffMember;

use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMember;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\StaffProfile;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class OnboardStaffMemberHandlerTest extends KernelTestCase
{
    use Factories;

    public function testOnboardingCreatesMembershipAndProfile(): void
    {
        ClinicDataStory::load();

        $user = ClinicUserFactory::new()
            ->withEmail('new-vet@kiveto.local')
            ->withPlainPassword('password')
            ->create()
        ;

        $commandBus = self::getContainer()->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);

        $profileId = $commandBus->dispatch(new OnboardStaffMember(
            clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
            userId: $user->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Sophie',
            lastName: 'Rousseau',
            displayName: 'Dr. Rousseau',
            agendaColor: '#1a2b3c',
            sortOrder: 0,
            isVisibleInAgenda: true,
        ));

        self::assertIsString($profileId);

        $profileRepo = self::getContainer()->get(StaffProfileRepositoryInterface::class);
        \assert($profileRepo instanceof StaffProfileRepositoryInterface);

        $profile = $profileRepo->findById(StaffProfileId::fromString($profileId));

        self::assertNotNull($profile);
        self::assertSame('Sophie', $profile->firstName());
        self::assertFalse($profile->hasVeterinaryCredentials());
    }

    public function testOnboardingAtomicityRollsBackMembershipOnProfileSaveFailure(): void
    {
        ClinicDataStory::load();

        $user = ClinicUserFactory::new()
            ->withEmail('atomic-test-vet@kiveto.local')
            ->withPlainPassword('password')
            ->create()
        ;

        $container = self::getContainer();

        $throwingRepo = new class implements StaffProfileRepositoryInterface {
            public function save(StaffProfile $profile): void
            {
                throw new \RuntimeException('forced failure for AC9');
            }

            public function findById(StaffProfileId $id): ?StaffProfile
            {
                throw new \LogicException('not used in this test');
            }

            public function findByMembershipId(ClinicMembershipId $membershipId): ?StaffProfile
            {
                throw new \LogicException('not used in this test');
            }
        };

        $container->set(StaffProfileRepositoryInterface::class, $throwingRepo);

        $commandBus = $container->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);

        $exceptionCaught = false;

        try {
            $commandBus->dispatch(new OnboardStaffMember(
                clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
                userId: $user->getId()->toRfc4122(),
                role: ClinicMemberRole::VETERINARY,
                engagement: ClinicMembershipEngagement::EMPLOYEE,
                firstName: 'Atomic',
                lastName: 'Test',
                displayName: 'Dr. Test',
                agendaColor: '#1a2b3c',
                sortOrder: 0,
                isVisibleInAgenda: true,
            ));
        } catch (\RuntimeException) {
            $exceptionCaught = true;
        }

        self::assertTrue($exceptionCaught, 'Expected RuntimeException from ThrowingStaffProfileRepository');

        $membershipRepo = $container->get(ClinicMembershipRepositoryInterface::class);
        \assert($membershipRepo instanceof ClinicMembershipRepositoryInterface);

        $userId   = UserId::fromString($user->getId()->toRfc4122());
        $clinicId = ClinicId::fromString(ClinicDataStory::INDEPENDENT_CLINIC_ID);

        $existing = $membershipRepo->findByClinicAndUser($clinicId, $userId);
        self::assertNull($existing, 'Membership must not exist after a rolled-back transaction.');
    }
}
