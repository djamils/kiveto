<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

final readonly class InvoiceSummaryView
{
    public function __construct(
        public MoneyView $total,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['unpaid', 'partially_paid', 'paid', 'credited']])]
        public string $paymentStatus,
    ) {
    }
}
