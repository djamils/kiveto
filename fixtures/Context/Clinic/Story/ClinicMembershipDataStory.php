<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Clinic\Story;

use App\Context\Clinic\Application\Command\Staff\CreateClinicMembership\CreateClinicMembership;
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
        // Retrieve existing users (created by ClinicVetStory)
        $vetUser = ClinicUserFactory::repository()->findOneBy(['email' => 'vet@kiveto.local']);

        if (null === $vetUser) {
            throw new \RuntimeException('vet@kiveto.local user not found. ClinicVetStory must be loaded first.');
        }

        // Create other users for testing
        $assistantUser = ClinicUserFactory::new()
            ->withEmail('assistant@kiveto.local')
            ->withPlainPassword('assistant')
            ->create()
        ;

        $adminUser = ClinicUserFactory::new()
            ->withEmail('admin.clinic@kiveto.local')
            ->withPlainPassword('admin')
            ->create()
        ;

        $contractorUser = ClinicUserFactory::new()
            ->withEmail('contractor@kiveto.local')
            ->withPlainPassword('contractor')
            ->create()
        ;

        // Third Paris veterinarian — intentionally has NO appointments in
        // SchedulingStory so the agenda's week view can verify an empty vet
        // column appears (see AC-Empty-veterinarian).
        $emptyParisVet = ClinicUserFactory::new()
            ->withEmail('vet2@kiveto.local')
            ->withPlainPassword('vet2')
            ->create()
        ;

        // Assign veterinarian to Paris clinic via command bus
        $this->commandBus->dispatch(new CreateClinicMembership(
            clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
            userId: $vetUser->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));

        // Assign veterinarian to Lyon clinic as well
        $this->commandBus->dispatch(new CreateClinicMembership(
            clinicId: ClinicDataStory::GROUP_CLINIC_ID,
            userId: $vetUser->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));

        // Assign assistant to Paris clinic
        $this->commandBus->dispatch(new CreateClinicMembership(
            clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
            userId: $assistantUser->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY_ASSISTANT,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));

        // Assign manager to Lyon clinic (group)
        $this->commandBus->dispatch(new CreateClinicMembership(
            clinicId: ClinicDataStory::GROUP_CLINIC_ID,
            userId: $adminUser->getId()->toRfc4122(),
            role: ClinicMemberRole::MANAGER,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));

        // Assign contractor to Paris clinic (limited validity)
        $validUntil = new \DateTimeImmutable('+6 months');
        $this->commandBus->dispatch(new CreateClinicMembership(
            clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
            userId: $contractorUser->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            validUntil: $validUntil,
        ));

        // Third Paris veterinarian membership — stays empty on purpose.
        $this->commandBus->dispatch(new CreateClinicMembership(
            clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
            userId: $emptyParisVet->getId()->toRfc4122(),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));
    }
}
