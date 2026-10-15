<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListConsultationRegister;

/**
 * One page of the register and the counters of the page header.
 */
final readonly class ConsultationRegisterPage
{
    /**
     * @param list<ConsultationRegisterItem> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public int $inProgress,
        public int $toClose,
        public int $today,
        public int $recorded,
    ) {
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
