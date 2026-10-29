<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Presentation\Api\Shared\Problem\ApiProblem;

/**
 * Echoes the payload and counts its executions, so tests can tell a replay
 * (counter unchanged) from a new execution. The message `rate-limited` throws
 * a 429 ApiProblem instead.
 *
 * @implements ProcessorInterface<IdempotentEcho, IdempotentEcho>
 */
final class IdempotentEchoProcessor implements ProcessorInterface
{
    private static int $executions = 0;

    public static function executions(): int
    {
        return self::$executions;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): IdempotentEcho
    {
        $data->executions = ++self::$executions;

        if ('rate-limited' === $data->message) {
            throw new ApiProblem(type: 'otp_rate_limited', status: 429, title: 'Too many requests', detail: 'Réessayez plus tard.', headers: ['Retry-After' => '120']);
        }

        return $data;
    }
}
