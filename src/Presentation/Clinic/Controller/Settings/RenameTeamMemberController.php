<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\RenameStaffProfile\RenameStaffProfile;
use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The first and last name of a member, from the « Profil » section.
 *
 * The name shown in the agenda follows when it was the full name or the last
 * name; one chosen by hand (« Dr Vétérinaire de garde ») is left alone.
 */
#[Route(path: '/settings/team/members/{membershipId}/name', name: 'clinic_settings_team_name', methods: ['POST'])]
final readonly class RenameTeamMemberController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private StaffProfileReadRepositoryInterface $profiles,
        private TeamEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request, string $membershipId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        if (null === $this->endpoint->member($membershipId)) {
            return $this->endpoint->unknown();
        }

        $profile = $this->profiles->findByMembershipId($membershipId);

        if (null === $profile) {
            return $this->endpoint->refuse('Ce membre n’a pas de profil : son nom ne se modifie pas ici.');
        }

        $firstName = TeamEndpoint::text($request, 'firstName');
        $lastName  = TeamEndpoint::text($request, 'lastName');

        if (null === $firstName || null === $lastName) {
            return $this->endpoint->refuse('Le prénom et le nom sont nécessaires.');
        }

        $displayName = match ($profile->displayName) {
            $profile->lastName                                      => $lastName,
            mb_trim($profile->firstName . ' ' . $profile->lastName) => $firstName . ' ' . $lastName,
            default                                                 => $profile->displayName,
        };

        try {
            $this->commandBus->dispatch(new RenameStaffProfile(
                profileId: $profile->profileId,
                firstName: $firstName,
                lastName: $lastName,
                displayName: mb_substr($displayName, 0, 60),
            ));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
