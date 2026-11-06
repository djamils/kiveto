<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Team;

use App\Presentation\Backoffice\Team\ConsoleTeam;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Équipe » (administrators): the members of the Kiveto team, the link of
 * an invitation just made (shown once), the invitation dialog.
 */
final class ShowTeamController extends AbstractController
{
    public const string CSRF_ID = 'backoffice_team';

    /** The invitation just made: its link is shown once, then forgotten. */
    public const string INVITATION_KEY = 'console.team_invitation';

    public function __construct(
        private readonly ConsoleTeam $team,
    ) {
    }

    #[Route(path: '/team', name: 'backoffice_team', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $session    = $request->getSession();
        $invitation = $session->get(self::INVITATION_KEY);
        $session->remove(self::INVITATION_KEY);

        return $this->render('backoffice/console/team/index.html.twig', [
            'members'     => $this->team->members(),
            'roles'       => ConsoleTeam::ROLES,
            'invitation'  => \is_array($invitation) ? $invitation : null,
            'open_dialog' => 'invite' === $request->query->getString('dialog') ? 'dialog-invite' : '',
        ]);
    }
}
