<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Presentation\Clinic\ViewModel\Team\TeamPageBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** The team again, asked by the page after each change. */
#[Route(path: '/settings/team/data', name: 'clinic_settings_team_data', methods: ['GET'])]
final readonly class TeamDataController
{
    public function __construct(
        private TeamPageBuilder $pages,
        private TeamEndpoint $endpoint,
        private ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_STAFF);

        return new JsonResponse($this->pages->data($this->endpoint->clinicId()));
    }
}
