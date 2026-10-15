<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\ClearVeterinaryCredentials\ClearVeterinaryCredentials;
use App\Context\Clinic\Application\Command\Staff\RegisterVeterinaryCredentials\RegisterVeterinaryCredentials;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Exercice vétérinaire »: the number a veterinarian is registered under with
 * the Ordre, and the title printed before their name. An empty number clears
 * the registration — the member then no longer prescribes.
 */
#[Route(path: '/settings/team/members/{membershipId}/credentials', name: 'clinic_settings_team_credentials', methods: ['POST'])]
final readonly class ChangeTeamMemberCredentialsController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private TeamEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request, string $membershipId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $member = $this->endpoint->member($membershipId);

        if (null === $member) {
            return $this->endpoint->unknown();
        }

        $profileId = $this->endpoint->profileId($membershipId);

        if (null === $profileId) {
            return $this->endpoint->refuse('Ce membre n’a pas de profil : il ne peut pas être inscrit à l’Ordre.');
        }

        $number = TeamEndpoint::text($request, 'registrationNumber');
        $title  = ProfessionalTitle::tryFrom($request->request->getString('title')) ?? ProfessionalTitle::DR;

        try {
            $this->commandBus->dispatch(null === $number
                ? new ClearVeterinaryCredentials($profileId)
                : new RegisterVeterinaryCredentials(profileId: $profileId, registrationNumber: $number, professionalTitle: $title));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\RuntimeException $exception) {
            // The role is not a veterinarian's.
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
