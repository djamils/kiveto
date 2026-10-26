<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Clinic\Story;

use App\Context\Clinic\Application\Command\Staff\CreateClinicMembership\CreateClinicMembership;
use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMember;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use Zenstruck\Foundry\Story;

final class ClinicMembershipDataStory extends Story
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function build(): void
    {
        $adminUser = ClinicUserFactory::new()
            ->withEmail('admin.clinic@kiveto.local')
            ->withPlainPassword('admin')
            ->create()
        ;

        // The Lyon manager holds the administration hat (explicit: the manager
        // role no longer implies it) and has a staff profile, so that what she
        // sends is signed with her name (« Claire Lambert vous invite… »).
        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: ClinicDataStory::GROUP_CLINIC_ID,
            userId: $adminUser->getId()->toRfc4122(),
            role: ClinicMemberRole::MANAGER,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Claire',
            lastName: 'Lambert',
            displayName: 'Claire Lambert',
            agendaColor: '#5f9ea0',
            sortOrder: 9,
            isVisibleInAgenda: false,
            isClinicAdministrator: true,
        ));

        // A Lyon member added from the back office keeps no profile: the team
        // page has to cope with it.
        $backOfficeMember = ClinicUserFactory::new()
            ->withEmail('secretariat.lyon@kiveto.local')
            ->withPlainPassword('secretariat')
            ->create()
        ;

        $this->commandBus->dispatch(new CreateClinicMembership(
            clinicId: ClinicDataStory::GROUP_CLINIC_ID,
            userId: $backOfficeMember->getId()->toRfc4122(),
            role: ClinicMemberRole::RECEPTIONIST,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));
    }
}
