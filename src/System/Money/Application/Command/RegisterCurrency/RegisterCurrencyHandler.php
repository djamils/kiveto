<?php

declare(strict_types=1);

namespace App\System\Money\Application\Command\RegisterCurrency;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Currency;
use App\System\Money\Domain\Repository\CurrencyRepository;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\CurrencySymbol;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegisterCurrencyHandler
{
    public function __construct(
        private CurrencyRepository $currencyRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegisterCurrency $command): void
    {
        $code     = CurrencyCode::fromString($command->code);
        $symbol   = CurrencySymbol::fromString($command->symbol);
        $decimals = CurrencyDecimals::of($command->decimals);

        $existing = $this->currencyRepository->findByCode($code);

        if (null === $existing) {
            $currency = Currency::create($code, $symbol, $decimals, $command->displayName, $this->clock);
            $this->currencyRepository->save($currency);
            $this->domainEventPublisher->publish($currency);

            return;
        }

        $existing->updateDisplay($symbol, $decimals, $command->displayName);
        $this->currencyRepository->save($existing);
    }
}
