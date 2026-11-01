<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\RequestDataExportProcessor;
use App\Presentation\Api\Portal\View\DataExportRequestView;

#[ApiResource(
    shortName: 'DataExportRequest',
    operations: [
        new Post(
            uriTemplate: '/me/data-export',
            status: 202,
            openapi: new Operation(
                tags: ['Compte'],
                summary: 'Demande un export des données du compte.',
                description: 'Construit en asynchrone (suivi par `GET /me/data-exports`), sans exigence d\'e-mail. Une archive ZIP (`schemaVersion: 1`) : `account.json`, `clinics/<clinicId>.json` par clinique rattachée, `summary.pdf`. Une adresse e-mail vérifiée reçoit seulement un avis, sans lien. Un export par 24 h ; le quota est rendu si la construction échoue.',
                responses: [
                    '202' => new Response(description: 'Export en cours de construction.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '429' => new Response(description: '`export_rate_limited` (`Retry-After`).'),
                ],
            ),
            input: false,
            output: DataExportRequestView::class,
            read: false,
            deserialize: false,
            validate: false,
            processor: RequestDataExportProcessor::class,
        ),
    ],
)]
final readonly class DataExportRequest
{
}
