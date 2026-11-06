<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Team;

use App\Presentation\Backoffice\Team\ConsoleTeam;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Inviter un membre » / « Nouveau lien »: the account and its 7-day link,
 * shown once on the team page for the admin to pass on.
 */
final class InviteTeamMemberController extends AbstractController
{
    public function __construct(
        private readonly ConsoleTeam $team,
    ) {
    }

    #[Route(path: '/team/invite', name: 'backoffice_team_invite', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(ShowTeamController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $invitation = $this->team->invite($request->request->getString('email'), $request->request->getString('name'), $request->request->getString('role', 'support'));
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('backoffice_team');
        }

        $request->getSession()->set(ShowTeamController::INVITATION_KEY, [
            'email'     => $invitation->email,
            'url'       => $invitation->url,
            'expiresAt' => $invitation->expiresAt->getTimestamp(),
        ]);

        return $this->redirectToRoute('backoffice_team');
    }
}
