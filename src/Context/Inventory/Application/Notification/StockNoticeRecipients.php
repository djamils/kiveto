<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Notification;

use App\Context\Inventory\Application\Port\ClinicStaffInterface;

/**
 * Who hears about the stock in the bell (D13): the clinic's veterinary
 * assistants, or its administrators when it has none.
 */
final readonly class StockNoticeRecipients
{
    public function __construct(private ClinicStaffInterface $staff)
    {
    }

    /**
     * @return list<string> user ids
     */
    public function of(string $clinicId): array
    {
        $assistants = $this->staff->assistants($clinicId);

        return [] !== $assistants ? $assistants : $this->staff->administrators($clinicId);
    }
}
