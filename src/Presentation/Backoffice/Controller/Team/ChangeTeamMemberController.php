<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Team;

use App\Presentation\Backoffice\Team\ConsoleTeam;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A member of the team: their role (`role`), or switched off and on
 * (`enabled`).
 */
final class ChangeTeamMemberController extends AbstractController
{
    public function __construct(
        private readonly ConsoleTeam $team,
    ) {
    }

    #[Route(path: '/team/{id}', name: 'backoffice_team_member', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(ShowTeamController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $member = $this->team->find($id) ?? throw $this->createNotFoundException('Membre de l’équipe introuvable.');

        try {
            if ($request->request->has('role')) {
                if ($this->team->changeRole($member, $request->request->getString('role'))) {
                    $this->addFlash('success', \sprintf('%s est maintenant %s.', $member->displayName, mb_strtolower(ConsoleTeam::ROLES[$request->request->getString('role')] ?? '')));
                }
            } else {
                $enabled = $request->request->getBoolean('enabled');
                $this->team->switch($member, $enabled);
                $this->addFlash('success', \sprintf($enabled ? '%s retrouve la console.' : '%s n’a plus accès à la console : ses sessions sont fermées.', $member->displayName));
            }
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('backoffice_team');
    }
}
