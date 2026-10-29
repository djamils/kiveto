<?php

declare(strict_types=1);

namespace App\Presentation\Portal\Controller\WellKnown;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** iOS universal links: invitation links open the owner app directly. */
final readonly class AppleAppSiteAssociationController
{
    public function __construct(
        #[Autowire('%env(PORTAL_IOS_APP_ID)%')]
        private string $appId,
    ) {
    }

    #[Route(path: '/.well-known/apple-app-site-association', name: 'portal_apple_app_site_association', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $response = new JsonResponse([
            'applinks' => [
                'details' => [[
                    'appIDs'     => [$this->appId],
                    'components' => [['/' => '/invitation*']],
                ]],
            ],
        ]);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
