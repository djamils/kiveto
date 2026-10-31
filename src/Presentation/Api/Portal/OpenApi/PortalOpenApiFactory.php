<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Documents the Portal routes served by `__invoke` controllers (files,
 * signed downloads, calendar), which API Platform does not see, so that
 * `make openapi-export` contains the whole contract.
 */
#[AsDecorator('api_platform.openapi.factory')]
final readonly class PortalOpenApiFactory implements OpenApiFactoryInterface
{
    private const string PREFIX = '/api/portal/v1';

    /**
     * path → [operationId, tag, summary, description, path parameters, success status, success description, success media type or null, public].
     *
     * @var array<string, array{string, string, string, string, list<string>, string, string, ?string, bool}>
     */
    private const array ROUTES = [
        '/clinics/{clinicId}/documents/{documentId}/file' => [
            'api_portal_document_file',
            'Documents',
            'Télécharger un document.',
            'Marque le document comme lu pour l\'appelant, puis redirige vers une URL signée valable 5 minutes.',
            ['clinicId', 'documentId'],
            '302',
            'Redirection vers l\'URL signée (`Cache-Control: private, no-store`).',
            null,
            false,
        ],
        '/files/documents/{clinicId}/{documentId}/{version}' => [
            'api_portal_document_download',
            'Fichiers',
            'Le PDF d\'un document (URL signée).',
            'Publique : la signature tient lieu d\'authentification, 5 minutes. Sert la version signée même si une plus récente a été publiée depuis.',
            ['clinicId', 'documentId', 'version'],
            '200',
            'Le PDF (`Cache-Control: private, no-store`).',
            'application/pdf',
            true,
        ],
    ];

    public function __construct(
        #[AutowireDecorated]
        private OpenApiFactoryInterface $decorated,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $paths   = $openApi->getPaths();

        foreach (self::ROUTES as $path => [$operationId, $tag, $summary, $description, $parameters, $status, $success, $mediaType, $public]) {
            $responses = [
                $status => new Response(
                    description: $success,
                    content: null === $mediaType ? null : new \ArrayObject([$mediaType => new MediaType(new \ArrayObject(['type' => 'string', 'format' => 'binary']))]),
                ),
                '404' => new Response(description: '`not_found`.'),
            ];

            if (!$public) {
                $responses['401'] = new Response(description: '`unauthorized` ou `token_expired`.');
            }

            $paths->addPath(self::PREFIX . $path, new PathItem(get: new Operation(
                operationId: $operationId,
                tags: [$tag],
                responses: $responses,
                summary: $summary,
                description: $description,
                parameters: array_map(
                    static fn (string $name): Parameter => new Parameter($name, 'path', required: true, schema: ['type' => 'string']),
                    $parameters,
                ),
                security: $public ? [] : null,
            )));
        }

        return $openApi;
    }
}
