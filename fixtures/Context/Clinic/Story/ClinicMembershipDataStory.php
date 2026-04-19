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
        $adminUser = ClinicUserFactory::new()
            ->withEmail('admin.clinic@kiveto.local')
            ->withPlainPassword('admin')
            ->create()
        ;

        // Assign manager to Lyon clinic (group) — non-practitioner, CreateClinicMembership is allowed
        $this->commandBus->dispatch(new CreateClinicMembership(
            clinicId: ClinicDataStory::GROUP_CLINIC_ID,
            userId: $adminUser->getId()->toRfc4122(),
            role: ClinicMemberRole::MANAGER,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));
    }
}
