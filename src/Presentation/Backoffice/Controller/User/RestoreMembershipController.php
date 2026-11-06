<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Rétablir » an access removed earlier.
 */
final class RestoreMembershipController extends AbstractUserActionController
{
    #[Route(path: '/users/{id}/memberships/{membershipId}/restore', name: 'backoffice_user_membership_restore', requirements: ['id' => ShowClinicController::UUID, 'membershipId' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id, string $membershipId): RedirectResponse
    {
        $account = $this->account($request, $id);
        $member  = $this->users->membership($id, $membershipId) ?? throw $this->createNotFoundException('Accès introuvable.');

        $this->users->restore($account, $member);
        $this->addFlash('success', \sprintf('Accès à %s rétabli.', $member->clinicName));

        return $this->back($id);
    }
}
