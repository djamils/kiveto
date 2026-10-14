<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\CloseCashDesk;

use App\Context\Billing\Application\Port\CashDeskReadRepositoryInterface;
use App\Context\Billing\Domain\CashClosing;
use App\Context\Billing\Domain\Exception\InvalidCashClosingException;
use App\Context\Billing\Domain\Repository\CashClosingRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\CashClosingId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Seals a business day: what the day's payments say the till holds, less what
 * credit notes handed back, against what was counted.
 *
 * @return int the closing's number (« ticket Z »)
 */
#[AsMessageHandler]
final readonly class CloseCashDeskHandler
{
    private const string DEFAULT_CURRENCY = 'EUR';

    public function __construct(
        private CashDeskReadRepositoryInterface $cashDesk,
        private CashClosingRepositoryInterface $closings,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CloseCashDesk $command): int
    {
        $clinicId = ClinicId::fromString($command->clinicId);

        if (null !== $this->closings->findForDate($clinicId, $command->businessDate)) {
            throw new InvalidCashClosingException(\sprintf('The day %s is already closed.', $command->businessDate));
        }

        $takings  = $this->cashDesk->takings($clinicId, $command->dayStart, $command->dayEnd);
        $expected = [];

        foreach ($takings->collectedByMethod as $method => $amount) {
            $expected[$method] = $amount;
        }

        foreach ($takings->refundedByMethod as $method => $amount) {
            $expected[$method] = ($expected[$method] ?? 0) - $amount;
        }

        $closing = CashClosing::close(
            id: CashClosingId::fromString($this->uuidGenerator->generate()),
            clinicId: $clinicId,
            number: $this->closings->nextNumber($clinicId),
            businessDate: $command->businessDate,
            expectedMinorUnits: $expected,
            countedCashMinorUnits: $command->countedCashMinorUnits,
            countedChequesMinorUnits: $command->countedChequesMinorUnits,
            comment: $command->comment,
            currency: CurrencyCode::fromString(self::DEFAULT_CURRENCY),
            now: $this->clock->now(),
            closedByUserId: $command->closedByUserId,
        );

        $this->closings->save($closing);

        return $closing->number();
    }
}
