<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\ReverifyStaleTaxProfiles;

use App\Context\Client\Application\Command\VerifyClientTaxProfile\VerifyClientTaxProfile;
use App\Context\Client\Application\Verification\TaxProfileVerificationScheduleInterface;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Re-requests profiles never checked, last answered by an outage, or checked
 * more than 30 days ago, on the batch queue (drained after the interactive
 * one, paced by the worker budgets). Idempotent: a second run right after
 * finds nothing, everything being requested less than 24 hours ago.
 */
#[AsMessageHandler]
final readonly class ReverifyStaleTaxProfilesHandler
{
    private const string MAX_AGE        = 'P30D';
    private const string PENDING_WINDOW = 'PT24H';

    public function __construct(
        private TaxProfileVerificationScheduleInterface $schedule,
        private ExternalApiCommandBusInterface $externalApiCommandBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ReverifyStaleTaxProfiles $command): void
    {
        $now             = $this->clock->now();
        $requestedBefore = $now->sub(new \DateInterval(self::PENDING_WINDOW));
        $stale           = $this->schedule->findStale($now->sub(new \DateInterval(self::MAX_AGE)), $requestedBefore);

        if ([] === $stale) {
            return;
        }

        $clinicOf = array_column($stale, 'clinicId', 'clientId');

        foreach ($this->schedule->markRequested(array_keys($clinicOf), $now, $requestedBefore) as $clientId) {
            $this->externalApiCommandBus->send(
                new VerifyClientTaxProfile($clinicOf[$clientId], $clientId),
                new TransportNamesStamp(['async_external_batch']),
            );
        }
    }
}
