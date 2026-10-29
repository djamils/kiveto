<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\LogoutProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'SessionLogout',
    operations: [
        new Post(
            uriTemplate: '/auth/logout',
            status: 204,
            openapi: new Operation(
                tags: ['Authentification'],
                summary: 'Révoque la famille du refresh token (déconnexion).',
                description: 'Sans effet si le jeton n\'appartient pas à l\'appelant. L\'access token reste valable jusqu\'à son expiration (15 min).',
                responses: [
                    '204' => new Response(description: 'Déconnecté.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                ],
            ),
            output: false,
            processor: LogoutProcessor::class,
        ),
    ],
)]
final class SessionLogout
{
    #[Assert\NotBlank]
    public string $refreshToken;
}
