<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Auth\Account;

use App\Presentation\Clinic\ViewModel\Auth\PublicAccountPage;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Query\CheckAccountToken\AccountTokenCheck;
use App\System\IdentityAccess\Application\Query\CheckAccountToken\CheckAccountToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The invitation link of the e-mail (public). Opened with its secret, it
 * keeps it in the session and reloads without it; then shows the « choose
 * your password » form, « Votre compte est déjà actif » or « Ce lien n'est
 * plus valide ».
 */
#[Route(path: '/invitation/{token}', name: 'clinic_invitation', requirements: ['token' => '[A-Za-z0-9_-]+'], defaults: ['token' => null], methods: ['GET'])]
final readonly class ShowInvitationController
{
    public const string CSRF_ID = 'invitation';

    public function __construct(
        private PublicAccountPage $page,
        private QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(Request $request, ?string $token = null): Response
    {
        if (null !== $token) {
            return $this->page->keepSecret($request, PublicAccountPage::INVITATION_SESSION_KEY, $token, 'clinic_invitation');
        }

        $secret = PublicAccountPage::secret($request, PublicAccountPage::INVITATION_SESSION_KEY);
        $check  = null !== $secret ? $this->queryBus->ask(new CheckAccountToken($secret, CheckAccountToken::INVITATION)) : null;

        if (!$check instanceof AccountTokenCheck) {
            return $this->page->render('security/invitation.html.twig', ['state' => 'invalid']);
        }

        return $this->page->render('security/invitation.html.twig', [
            'state' => match ($check->state) {
                AccountTokenCheck::VALID          => 'form',
                AccountTokenCheck::ALREADY_ACTIVE => 'active',
                default                           => 'invalid',
            },
            'email' => $check->email,
        ]);
    }
}
