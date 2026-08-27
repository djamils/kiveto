<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\ValueObject;

use App\Context\Clinic\Domain\Exception\InvalidOpeningHoursException;

/**
 * When the clinic is open.
 *
 * A week holds seven days; a day holds the slots it is open for — none at all
 * when it is closed, two when it shuts at noon. On top of the week sit the
 * exceptional closures: the days the clinic is shut whatever the week says.
 */
final readonly class OpeningHours
{
    public const int MONDAY = 1;
    public const int SUNDAY = 7;

    /**
     * @param array<int, list<OpeningSlot>> $week     keyed by ISO day, 1 (Monday) to 7 (Sunday)
     * @param list<ExceptionalClosure>      $closures
     */
    private function __construct(
        private array $week,
        public array $closures,
    ) {
    }

    public static function empty(): self
    {
        return new self([], []);
    }

    /**
     * @param array<int, list<OpeningSlot>> $week
     * @param list<ExceptionalClosure>      $closures
     */
    public static function create(array $week, array $closures = []): self
    {
        $clean = [];

        foreach ($week as $day => $slots) {
            if ($day < self::MONDAY || $day > self::SUNDAY) {
                throw InvalidOpeningHoursException::unknownDay($day);
            }

            if ([] === $slots) {
                continue;
            }

            usort($slots, static fn (OpeningSlot $a, OpeningSlot $b): int => strcmp($a->opensAt, $b->opensAt));

            $previous = null;

            foreach ($slots as $slot) {
                // Two slots that overlap would make "open until" meaningless.
                if (null !== $previous && strcmp($slot->opensAt, $previous->closesAt) < 0) {
                    throw InvalidOpeningHoursException::overlappingSlots($day);
                }

                $previous = $slot;
            }

            $clean[$day] = $slots;
        }

        ksort($clean);

        return new self($clean, $closures);
    }

    /**
     * @return list<OpeningSlot>
     */
    public function slotsOn(int $day): array
    {
        return $this->week[$day] ?? [];
    }

    public function isClosedOn(int $day): bool
    {
        return [] === $this->slotsOn($day);
    }

    /**
     * @return array<int, list<OpeningSlot>>
     */
    public function week(): array
    {
        return $this->week;
    }

    public function isEmpty(): bool
    {
        return [] === $this->week && [] === $this->closures;
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    /**
     * @return array{week: array<int, list<array{opensAt: string, closesAt: string}>>, closures: list<array{date: string, reason: string|null}>}
     */
    public function toArray(): array
    {
        $week = [];

        foreach ($this->week as $day => $slots) {
            $week[$day] = array_map(
                static fn (OpeningSlot $slot): array => ['opensAt' => $slot->opensAt, 'closesAt' => $slot->closesAt],
                $slots,
            );
        }

        return [
            'week'     => $week,
            'closures' => array_map(
                static fn (ExceptionalClosure $closure): array => ['date' => $closure->date, 'reason' => $closure->reason],
                $this->closures,
            ),
        ];
    }

    /**
     * Rebuilds a week from what a column holds. The shape is checked here rather
     * than trusted: a row written by an older version, or by hand, must not walk
     * into the domain unexamined.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $week    = [];
        $rawWeek = $data['week'] ?? [];

        if (\is_array($rawWeek)) {
            foreach ($rawWeek as $day => $slots) {
                if (!\is_array($slots)) {
                    continue;
                }

                foreach ($slots as $slot) {
                    if (!\is_array($slot)) {
                        continue;
                    }

                    $week[(int) $day][] = OpeningSlot::create(
                        \is_string($slot['opensAt'] ?? null) ? $slot['opensAt'] : '',
                        \is_string($slot['closesAt'] ?? null) ? $slot['closesAt'] : '',
                    );
                }
            }
        }

        $closures    = [];
        $rawClosures = $data['closures'] ?? [];

        if (\is_array($rawClosures)) {
            foreach ($rawClosures as $closure) {
                if (!\is_array($closure) || !\is_string($closure['date'] ?? null)) {
                    continue;
                }

                $reason = $closure['reason'] ?? null;

                $closures[] = ExceptionalClosure::create($closure['date'], \is_string($reason) ? $reason : null);
            }
        }

        return self::create($week, $closures);
    }
}
