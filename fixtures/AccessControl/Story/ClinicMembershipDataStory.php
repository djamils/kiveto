<?php

declare(strict_types=1);

namespace App\Fixtures\AccessControl\Story;

use App\Fixtures\AccessControl\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Clinic\Story\ClinicDataStory;
use App\Fixtures\IdentityAccess\Factory\ClinicUserFactory;
use Zenstruck\Foundry\Story;

final class ClinicMembershipDataStory extends Story
{
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

        // Assign veterinarian to Paris clinic
        ClinicMembershipEntityFactory::new()
            ->withClinicId(ClinicDataStory::INDEPENDENT_CLINIC_ID)
            ->withUserId($vetUser->getId()->toRfc4122())
            ->asVeterinary()
            ->asEmployee()
            ->create()
        ;

        // Assign veterinarian to Lyon clinic as well
        ClinicMembershipEntityFactory::new()
            ->withClinicId(ClinicDataStory::GROUP_CLINIC_ID)
            ->withUserId($vetUser->getId()->toRfc4122())
            ->asVeterinary()
            ->asEmployee()
            ->create()
        ;

        // Assign assistant to Paris clinic
        ClinicMembershipEntityFactory::new()
            ->withClinicId(ClinicDataStory::INDEPENDENT_CLINIC_ID)
            ->withUserId($assistantUser->getId()->toRfc4122())
            ->asAssistantVeterinary()
            ->asEmployee()
            ->create()
        ;

        // Assign admin to Lyon clinic (group)
        ClinicMembershipEntityFactory::new()
            ->withClinicId(ClinicDataStory::GROUP_CLINIC_ID)
            ->withUserId($adminUser->getId()->toRfc4122())
            ->asClinicAdmin()
            ->asEmployee()
            ->create()
        ;

        // Assign contractor to Paris clinic (limited validity)
        $validUntil = new \DateTimeImmutable('+6 months');
        ClinicMembershipEntityFactory::new()
            ->withClinicId(ClinicDataStory::INDEPENDENT_CLINIC_ID)
            ->withUserId($contractorUser->getId()->toRfc4122())
            ->asVeterinary()
            ->asContractor($validUntil)
            ->create()
        ;

        // Create some additional memberships for testing
        ClinicMembershipEntityFactory::createMany(5);
    }
}
