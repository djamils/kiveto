<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel\Clinic;

use App\Context\Clinic\Application\Query\Console\ConsoleClinicRow;

/**
 * One line of the clinics table.
 */
final readonly class ClinicListRow
{
    public function __construct(
        public ConsoleClinicRow $clinic,
        public ?string $place,
        public ClinicLifecycle $lifecycle,
        public int $members,
        public int $pending,
        public ?\DateTimeImmutable $lastLogin,
        public ?int $features = null,
    ) {
    }
}
