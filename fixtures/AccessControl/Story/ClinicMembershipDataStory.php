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
        // Créer des users pour les tests
        $vetUser = ClinicUserFactory::new()
            ->withEmail('vet@kiveto.local')
            ->withPlainPassword('vet')
            ->create()
        ;

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

        // Assigner le vétérinaire à la clinic Paris
        ClinicMembershipEntityFactory::new()
            ->withClinicId(ClinicDataStory::INDEPENDENT_CLINIC_ID)
            ->withUserId($vetUser->getId()->toRfc4122())
            ->asVeterinary()
            ->asEmployee()
            ->create()
        ;

        // Assigner l'assistant à la clinic Paris
        ClinicMembershipEntityFactory::new()
            ->withClinicId(ClinicDataStory::INDEPENDENT_CLINIC_ID)
            ->withUserId($assistantUser->getId()->toRfc4122())
            ->asAssistantVeterinary()
            ->asEmployee()
            ->create()
        ;

        // Assigner l'admin à la clinic Lyon (groupe)
        ClinicMembershipEntityFactory::new()
            ->withClinicId(ClinicDataStory::GROUP_CLINIC_ID)
            ->withUserId($adminUser->getId()->toRfc4122())
            ->asClinicAdmin()
            ->asEmployee()
            ->create()
        ;

        // Assigner le contractor à la clinic Paris (validité limitée)
        $validUntil = new \DateTimeImmutable('+6 months');
        ClinicMembershipEntityFactory::new()
            ->withClinicId(ClinicDataStory::INDEPENDENT_CLINIC_ID)
            ->withUserId($contractorUser->getId()->toRfc4122())
            ->asVeterinary()
            ->asContractor($validUntil)
            ->create()
        ;

        // Créer quelques memberships supplémentaires pour les tests
        ClinicMembershipEntityFactory::createMany(5);
    }
}
