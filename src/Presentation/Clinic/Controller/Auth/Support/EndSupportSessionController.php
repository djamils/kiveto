<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Auth\Support;

use App\Presentation\Clinic\Support\SupportSessionCloser;
use App\Presentation\Clinic\Support\SupportSessionState;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * D2 → D3 — « Terminer la session »: back to the clinic's sheet in the
 * console, with what the session was.
 */
final class EndSupportSessionController extends AbstractController
{
    public const string CSRF_ID = 'clinic_support_end';

    public function __construct(
        private readonly SupportSessionCloser $closer,
    ) {
    }

    #[Route(path: '/support/end', name: 'clinic_support_end', methods: ['POST'], priority: 10)]
    public function __invoke(Request $request): Response
    {
        $state = SupportSessionState::of($request->getSession());

        if (null === $state) {
            return $this->redirectToRoute('clinic_login');
        }

        if (!$this->isCsrfTokenValid(self::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        return $this->closer->close($request, $state);
    }
}
