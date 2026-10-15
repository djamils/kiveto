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

        // A clinic runs itself: someone holds the keys to the settings, and
        // someone answers the phone. Administration is a hat now, not a role, so
        // the lead veterinarian wears it too — that is the independent practice
        // the demo is meant to show. The manager keeps it as well, which covers
        // the non-clinical administrator, and everyone else goes without so the
        // refused case stays represented.
        $managerUser = ClinicUserFactory::new()
            ->withEmail('manager@kiveto.local')
            ->withPlainPassword('manager')
            ->create()
        ;

        $receptionUser = ClinicUserFactory::new()
            ->withEmail('accueil@kiveto.local')
            ->withPlainPassword('accueil')
            ->create()
        ;

        $lyonVet = ClinicUserFactory::new()
            ->withEmail('vet.lyon@kiveto.local')
            ->withPlainPassword('vetlyon')
            ->create()
        ;

        $lyonAssistant = ClinicUserFactory::new()
            ->withEmail('assistant.lyon@kiveto.local')
            ->withPlainPassword('assistantlyon')
            ->create()
        ;

        // Onboard veterinarian to Paris clinic with credentials — a partner of
        // the practice he runs.
        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
            userId: $vetUser->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::PARTNER,
            firstName: 'Éric',
            lastName: 'Rousseau',
            displayName: 'Rousseau',
            agendaColor: '#4f86c6',
            sortOrder: 0,
            isVisibleInAgenda: true,
            registrationNumber: 'FR-12345',
            professionalTitle: ProfessionalTitle::DR,
            isClinicAdministrator: true,
        ));

        // The manager and the reception are named on the team page like
        // everyone else, so they get a profile too — kept out of the agenda,
        // where only those who see patients have a column.
        foreach ([ClinicDataStory::INDEPENDENT_CLINIC_ID, ClinicDataStory::GROUP_CLINIC_ID] as $clinicId) {
            $this->commandBus->dispatch(new OnboardStaffMember(
                clinicId: $clinicId,
                userId: $managerUser->getId()->toRfc4122(),
                role: ClinicMemberRole::MANAGER,
                engagement: ClinicMembershipEngagement::EMPLOYEE,
                firstName: 'Nadia',
                lastName: 'Ferrand',
                displayName: 'Nadia Ferrand',
                agendaColor: '#c4895b',
                sortOrder: 9,
                isVisibleInAgenda: false,
                phone: '+33611223344',
                isClinicAdministrator: true,
            ));
        }

        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
            userId: $receptionUser->getId()->toRfc4122(),
            role: ClinicMemberRole::RECEPTIONIST,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Inès',
            lastName: 'Moreau',
            displayName: 'Inès Moreau',
            agendaColor: '#4fb3bf',
            sortOrder: 8,
            isVisibleInAgenda: false,
            phone: '+33656789012',
        ));

        // ... and to the quiet clinic of the group (the dashboard's calm day).
        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: ClinicDataStory::CALM_CLINIC_ID,
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
            isClinicAdministrator: true,
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
            isClinicAdministrator: true,
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

        // Second Lyon veterinarian, so the agenda and the consultation filters
        // have more than one practitioner to sort on outside Paris.
        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: ClinicDataStory::GROUP_CLINIC_ID,
            userId: $lyonVet->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Sarah',
            lastName: 'Lemoine',
            displayName: 'Lemoine',
            agendaColor: '#8f6dbe',
            sortOrder: 1,
            isVisibleInAgenda: true,
            registrationNumber: 'FR-24680',
            professionalTitle: ProfessionalTitle::DR,
        ));

        // Lyon assistant (no credentials)
        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: ClinicDataStory::GROUP_CLINIC_ID,
            userId: $lyonAssistant->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY_ASSISTANT,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Karim',
            lastName: 'Benali',
            displayName: 'Karim Benali',
            agendaColor: '#d9a441',
            sortOrder: 2,
            isVisibleInAgenda: true,
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
