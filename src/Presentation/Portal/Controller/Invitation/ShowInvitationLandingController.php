<?php

declare(strict_types=1);

namespace App\Presentation\Portal\Controller\Invitation;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Where an invitation link lands when the app is not installed. The token
 * travels in the URL fragment (`#t=`), which browsers never send: this page
 * never sees it, and leaks no referrer either.
 */
final class ShowInvitationLandingController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(PORTAL_APP_STORE_URL)%')]
        private readonly string $appStoreUrl,
        #[Autowire('%env(PORTAL_PLAY_STORE_URL)%')]
        private readonly string $playStoreUrl,
    ) {
    }

    #[Route(path: '/invitation', name: 'portal_invitation_landing', methods: ['GET'])]
    public function __invoke(): Response
    {
        $response = $this->render('portal/invitation/landing.html.twig', [
            'appStoreUrl'  => $this->appStoreUrl,
            'playStoreUrl' => $this->playStoreUrl,
        ]);
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
