<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Exception\UnsupportedRecurrencePattern;

final readonly class RecurrenceRule
{
    public const string NONE     = 'NONE';
    public const string DAILY    = 'DAILY';
    public const string WEEKLY   = 'WEEKLY';
    public const string WEEKDAYS = 'WEEKDAYS';

    private const array VALID_FREQS = [self::NONE, self::DAILY, self::WEEKLY, self::WEEKDAYS];

    private function __construct(
        private string $freq,
        private ?string $until = null,
    ) {
        if (!\in_array($freq, self::VALID_FREQS, true)) {
            throw new UnsupportedRecurrencePattern($freq);
        }
    }

    public static function none(): self
    {
        return new self(self::NONE);
    }

    public static function daily(?string $until = null): self
    {
        return new self(self::DAILY, $until);
    }

    public static function weekly(?string $until = null): self
    {
        return new self(self::WEEKLY, $until);
    }

    public static function weekdays(?string $until = null): self
    {
        return new self(self::WEEKDAYS, $until);
    }

    public function freq(): string
    {
        return $this->freq;
    }

    public function until(): ?string
    {
        return $this->until;
    }

    public function isRecurring(): bool
    {
        return self::NONE !== $this->freq;
    }

    public function toJson(): string
    {
        return json_encode(['freq' => $this->freq, 'until' => $this->until], \JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Corrupt RecurrenceRule JSON in database: ' . $e->getMessage(), 0, $e);
        }

        if (!\is_array($decoded) || !isset($decoded['freq']) || !\is_string($decoded['freq'])) {
            throw new \RuntimeException('Corrupt RecurrenceRule JSON: missing or invalid "freq" key.');
        }

        $freq  = $decoded['freq'];
        $until = isset($decoded['until']) && \is_string($decoded['until']) ? $decoded['until'] : null;

        return new self($freq, $until);
    }
}
