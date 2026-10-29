<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

/**
 * Test-only resource (mapped under when@test only, never in the OpenAPI export)
 * exercising the IdempotentProcessor decorator.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/_test/idempotent-echo',
            processor: IdempotentEchoProcessor::class,
            extraProperties: ['idempotent' => true],
        ),
        new Post(
            uriTemplate: '/_test/echo',
            processor: IdempotentEchoProcessor::class,
        ),
    ],
)]
final class IdempotentEcho
{
    public string $message = '';

    /** Number of times the processor ran in this PHP process, set on output. */
    public int $executions = 0;
}
