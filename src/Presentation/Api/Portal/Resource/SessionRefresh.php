<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\RefreshSessionProcessor;
use App\Presentation\Api\Portal\View\SessionTokensView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'SessionRefresh',
    operations: [
        new Post(
            uriTemplate: '/auth/refresh',
            status: 200,
            openapi: new Operation(
                tags: ['Authentification'],
                summary: 'Échange un refresh token contre une nouvelle paire.',
                description: 'Rotation à chaque usage. Le client doit sérialiser ses refresh : rejouer un jeton plus de 30 s après son usage, ou deux fois, révoque toute la famille.',
                responses: [
                    '200' => new Response(description: 'Nouvelle paire.'),
                    '401' => new Response(description: '`refresh_revoked` : se reconnecter par code.'),
                ],
            ),
            output: SessionTokensView::class,
            processor: RefreshSessionProcessor::class,
        ),
    ],
)]
final class SessionRefresh
{
    #[Assert\NotBlank]
    public string $refreshToken;
}
