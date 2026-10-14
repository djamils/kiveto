<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalDirectoryFacts;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Medical alerts and due reminders of the animals listed on a directory page.
 */
final readonly class ListAnimalDirectoryFacts implements QueryInterface
{
    /**
     * A reminder is shown once its next dose falls within this many days —
     * the same horizon as the animal sheet's « À prévoir ».
     */
    public const int DUE_HORIZON_DAYS = 30;

    /**
     * @param list<string> $animalIds
     */
    public function __construct(
        public string $clinicId,
        public array $animalIds,
        public ?\DateTimeImmutable $asOf = null,
    ) {
    }

    /**
     * Last day a next dose may fall on to count as due.
     */
    public static function dueBy(?\DateTimeImmutable $asOf): \DateTimeImmutable
    {
        return ($asOf ?? new \DateTimeImmutable('today'))
            ->setTime(0, 0)
            ->modify(\sprintf('+%d days', self::DUE_HORIZON_DAYS))
        ;
    }
}
