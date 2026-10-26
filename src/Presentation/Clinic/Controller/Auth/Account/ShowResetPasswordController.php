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
 * The reset link of the e-mail (public). Opened with its secret, it keeps it
 * in the session and reloads without it; then shows the new-password form,
 * or « Ce lien n'est plus valide ».
 */
#[Route(path: '/reinitialiser/{token}', name: 'clinic_reset_password', requirements: ['token' => '[A-Za-z0-9_-]+'], defaults: ['token' => null], methods: ['GET'])]
final readonly class ShowResetPasswordController
{
    public const string CSRF_ID = 'reset_password';

    public function __construct(
        private PublicAccountPage $page,
        private QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(Request $request, ?string $token = null): Response
    {
        if (null !== $token) {
            return $this->page->keepSecret($request, PublicAccountPage::RESET_SESSION_KEY, $token, 'clinic_reset_password');
        }

        $secret = PublicAccountPage::secret($request, PublicAccountPage::RESET_SESSION_KEY);
        $check  = null !== $secret ? $this->queryBus->ask(new CheckAccountToken($secret, CheckAccountToken::PASSWORD_RESET)) : null;

        if (!$check instanceof AccountTokenCheck || !$check->isValid()) {
            return $this->page->render('security/reset_password.html.twig', ['state' => 'invalid']);
        }

        return $this->page->render('security/reset_password.html.twig', ['state' => 'form', 'email' => $check->email]);
    }
}
