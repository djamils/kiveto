<?php

declare(strict_types=1);

namespace App\Presentation\Portal\Controller\WellKnown;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Android app links: invitation links open the owner app directly. */
final readonly class AssetLinksController
{
    public function __construct(
        #[Autowire('%env(PORTAL_ANDROID_PACKAGE)%')]
        private string $package,
        #[Autowire('%env(PORTAL_ANDROID_CERT_FINGERPRINTS)%')]
        private string $fingerprints,
    ) {
    }

    #[Route(path: '/.well-known/assetlinks.json', name: 'portal_asset_links', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $response = new JsonResponse([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target'   => [
                'namespace'                => 'android_app',
                'package_name'             => $this->package,
                'sha256_cert_fingerprints' => array_values(array_filter(array_map(trim(...), explode(',', $this->fingerprints)))),
            ],
        ]]);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
