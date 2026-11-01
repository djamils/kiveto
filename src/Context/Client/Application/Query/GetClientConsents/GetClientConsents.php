<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientConsents;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The consents of some clients of a clinic (the Portal's « Mes
 * consentements »). Answers array<string, ClientConsentsView> keyed by
 * client id; a client of another clinic or unknown is absent.
 */
final readonly class GetClientConsents implements QueryInterface
{
    /**
     * @param list<string> $clientIds
     */
    public function __construct(
        public string $clinicId,
        public array $clientIds,
    ) {
    }
}
