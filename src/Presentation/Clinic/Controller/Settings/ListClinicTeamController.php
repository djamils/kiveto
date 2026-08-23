<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\ListAllMemberships;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\MembershipCollection;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Who works here: every membership of the clinic with the profile behind it.
 */
#[Route(path: '/settings/team', name: 'clinic_settings_team', methods: ['GET'])]
final class ListClinicTeamController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly StaffProfileReadRepositoryInterface $profiles,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_STAFF);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId->toString()));
        \assert($settings instanceof ClinicSettingsView);

        $memberships = $this->queryBus->ask(new ListAllMemberships(clinicId: $clinicId->toString()));
        \assert($memberships instanceof MembershipCollection);

        // The profile carries the name, the colour and the credentials; the
        // membership carries the role and whether it still stands.
        $membershipIds = array_map(
            static fn ($membership): string => $membership->membershipId,
            $memberships->memberships,
        );

        $profilesByMembership = [];

        foreach ($this->profiles->findByMembershipIds($membershipIds) as $profile) {
            $profilesByMembership[$profile->membershipId] = $profile;
        }

        return $this->render('clinic/settings/team.html.twig', [
            'settings'    => $settings,
            'memberships' => $memberships->memberships,
            'profiles'    => $profilesByMembership,
        ]);
    }
}
