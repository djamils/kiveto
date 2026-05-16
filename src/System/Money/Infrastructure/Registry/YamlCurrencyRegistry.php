<?php

declare(strict_types=1);

namespace App\System\Money\Infrastructure\Registry;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Exception\UnknownCurrencyException;
use App\System\Money\Domain\Service\CurrencyRegistry;
use App\System\Money\Domain\ValueObject\Currency;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\CurrencySymbol;
use Symfony\Component\Yaml\Yaml;

/**
 * In-memory currency catalogue loaded once at construction from a YAML resource.
 *
 * The Symfony service container instantiates this as a singleton, so the YAML
 * is parsed once per process; subsequent lookups are pure array reads.
 */
final readonly class YamlCurrencyRegistry implements CurrencyRegistry
{
    /** @var array<string, Currency> */
    private array $currencies;

    public function __construct(string $catalogPath)
    {
        $data = Yaml::parseFile($catalogPath);

        if (!\is_array($data) || !isset($data['currencies']) || !\is_array($data['currencies'])) {
            throw new \RuntimeException(\sprintf(
                'Invalid currency catalogue at "%s": missing "currencies" key.',
                $catalogPath,
            ));
        }

        $currencies = [];

        foreach ($data['currencies'] as $code => $entry) {
            $this->assertValidEntry($catalogPath, $code, $entry);
            \assert(\is_string($code) && \is_array($entry));
            \assert(\is_string($entry['symbol']) && \is_int($entry['decimals']) && \is_string($entry['displayName']));

            $currencies[$code] = Currency::of(
                CurrencyCode::fromString($code),
                CurrencySymbol::fromString($entry['symbol']),
                CurrencyDecimals::of($entry['decimals']),
                $entry['displayName'],
            );
        }

        $this->currencies = $currencies;
    }

    public function get(CurrencyCode $code): Currency
    {
        $key = $code->toString();

        if (!isset($this->currencies[$key])) {
            throw new UnknownCurrencyException($code);
        }

        return $this->currencies[$key];
    }

    /** @return list<Currency> */
    public function listAll(): array
    {
        return array_values($this->currencies);
    }

    public function has(CurrencyCode $code): bool
    {
        return isset($this->currencies[$code->toString()]);
    }

    private function assertValidEntry(string $catalogPath, mixed $code, mixed $entry): void
    {
        $invalid = !\is_string($code)
            || !\is_array($entry)
            || !\is_string($entry['symbol'] ?? null)
            || !\is_int($entry['decimals'] ?? null)
            || !\is_string($entry['displayName'] ?? null);

        if ($invalid) {
            throw new \RuntimeException(\sprintf(
                'Invalid currency entry "%s" in catalogue "%s".',
                (string) (\is_scalar($code) ? $code : ''),
                $catalogPath,
            ));
        }
    }
}
