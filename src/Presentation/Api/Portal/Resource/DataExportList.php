<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\DataExportListProvider;
use App\Presentation\Api\Portal\View\DataExportView;

/** The exports of the account; the route answers a plain JSON array of them. */
#[ApiResource(
    shortName: 'DataExportList',
    operations: [
        new GetCollection(
            uriTemplate: '/me/data-exports',
            openapi: new Operation(
                tags: ['Compte'],
                summary: 'Les exports de données du compte.',
                description: 'Les plus récents d\'abord. `ready` : l\'archive se télécharge par `GET /me/data-exports/{id}/file` jusqu\'à `expiresAt` (48 h), puis l\'export est `expired`. `failed` : une nouvelle demande est acceptée aussitôt.',
                responses: [
                    '200' => new Response(description: 'Les exports.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                ],
            ),
            output: DataExportView::class,
            provider: DataExportListProvider::class,
        ),
    ],
)]
final readonly class DataExportList
{
}
