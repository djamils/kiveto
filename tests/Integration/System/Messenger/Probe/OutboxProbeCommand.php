<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Messenger\Probe;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Test-only command: saves a client, publishes an integration event, then optionally fails.
 */
final readonly class OutboxProbeCommand implements CommandInterface
{
    public const string FAIL_NONE          = 'none';
    public const string FAIL_AFTER_PUBLISH = 'after_publish';

    public function __construct(
        public string $clinicId,
        public string $clientId,
        public string $failAt = self::FAIL_NONE,
    ) {
    }
}
