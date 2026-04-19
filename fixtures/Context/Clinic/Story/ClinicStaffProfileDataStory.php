<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Clinic\Story;

use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMember;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use Zenstruck\Foundry\Story;

final class ClinicStaffProfileDataStory extends Story
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function build(): void
    {
        $vetUser = ClinicUserFactory::repository()->findOneBy(['email' => 'vet@kiveto.local']);

        if (null === $vetUser) {
            throw new \RuntimeException('vet@kiveto.local user not found. ClinicVetStory must be loaded first.');
        }

        $assistantUser = ClinicUserFactory::new()
            ->withEmail('assistant@kiveto.local')
            ->withPlainPassword('assistant')
            ->create()
        ;

        $contractorUser = ClinicUserFactory::new()
            ->withEmail('contractor@kiveto.local')
            ->withPlainPassword('contractor')
            ->create()
        ;

        $emptyParisVet = ClinicUserFactory::new()
            ->withEmail('vet2@kiveto.local')
            ->withPlainPassword('vet2')
            ->create()
        ;

        // Onboard veterinarian to Paris clinic with credentials
        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
            userId: $vetUser->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Éric',
            lastName: 'Rousseau',
            displayName: 'Rousseau',
            agendaColor: '#4f86c6',
            sortOrder: 0,
            isVisibleInAgenda: true,
            registrationNumber: 'FR-12345',
            professionalTitle: ProfessionalTitle::DR,
        ));

        // Onboard the same veterinarian to Lyon clinic as well
        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: ClinicDataStory::GROUP_CLINIC_ID,
            userId: $vetUser->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Éric',
            lastName: 'Rousseau',
            displayName: 'Rousseau',
            agendaColor: '#4f86c6',
            sortOrder: 0,
            isVisibleInAgenda: true,
            registrationNumber: 'FR-12345',
            professionalTitle: ProfessionalTitle::DR,
        ));

        // Onboard assistant to Paris clinic (no credentials)
        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
            userId: $assistantUser->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY_ASSISTANT,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Julie',
            lastName: 'Martin',
            displayName: 'Julie Martin',
            agendaColor: '#e27a6d',
            sortOrder: 1,
            isVisibleInAgenda: true,
        ));

        // Contractor veterinarian for Paris (limited validity)
        $validUntil = new \DateTimeImmutable('+6 months');
        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
            userId: $contractorUser->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            firstName: 'Marc',
            lastName: 'Dupont',
            displayName: 'Dupont',
            agendaColor: '#6dbe78',
            sortOrder: 2,
            isVisibleInAgenda: true,
            validUntil: $validUntil,
            registrationNumber: 'FR-67890',
            professionalTitle: ProfessionalTitle::DR,
        ));

        // Third Paris veterinarian — intentionally has NO appointments.
        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
            userId: $emptyParisVet->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Lucie',
            lastName: 'Bernard',
            displayName: 'Bernard',
            agendaColor: '#c67db5',
            sortOrder: 3,
            isVisibleInAgenda: true,
            registrationNumber: 'FR-11111',
            professionalTitle: ProfessionalTitle::DR,
        ));
    }
}
