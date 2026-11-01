<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\RequestAccountDeletionProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'DeletionRequest',
    operations: [
        new Post(
            uriTemplate: '/me/deletion-request',
            status: 202,
            openapi: new Operation(
                tags: ['Compte'],
                summary: 'Supprime le compte, confirmé par le code SMS.',
                description: 'Le code de `POST /me/deletion-request/otp` est vérifié. Dès la 202, le compte est désactivé : tout access token et tout refresh répondent 401. En asynchrone : liens, invitations en attente, jetons et challenges du numéro révoqués ; exports supprimés ; profil et compte anonymisés (« Compte supprimé », téléphone et e-mail effacés). Les fiches `Client` des cliniques et leurs consentements ne sont pas modifiés. Une nouvelle invitation vers ce numéro crée un nouveau compte.',
                responses: [
                    '202' => new Response(description: 'Suppression engagée.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '422' => new Response(description: '`otp_invalid` (code faux, de connexion ou d\'un autre utilisateur), `otp_expired`, `otp_attempts_exceeded` ou `validation_failed`.'),
                ],
            ),
            output: false,
            processor: RequestAccountDeletionProcessor::class,
        ),
    ],
)]
final class DeletionRequest
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $challengeId;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Le code comporte 6 chiffres.')]
    public string $code;
}
