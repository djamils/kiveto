<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\Presentation\Api\Portal\State\CurrentTermsProvider;

#[ApiResource(
    shortName: 'CurrentTerms',
    operations: [
        new Get(
            uriTemplate: '/legal/terms/current',
            openapi: new Operation(
                tags: ['CGU'],
                summary: 'Version courante des conditions d\'utilisation du Portal.',
                description: 'Publique. `version` est la valeur attendue par les routes d\'acceptation (`termsVersion`).',
            ),
            provider: CurrentTermsProvider::class,
        ),
    ],
)]
final readonly class CurrentTerms
{
    public function __construct(
        public string $version,
        public string $url,
    ) {
    }
}
