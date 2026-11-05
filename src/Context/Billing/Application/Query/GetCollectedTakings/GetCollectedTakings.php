<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetCollectedTakings;

use App\Shared\Application\Bus\QueryInterface;

/**
 * What the clinic collected over a period, in minor units: GetBillingStats'
 * first figure alone, for a comparison that needs nothing else (the
 * dashboard's « même jour la semaine dernière »). Answers an int.
 */
final readonly class GetCollectedTakings implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
    ) {
    }
}
