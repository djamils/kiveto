<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Policy;

use App\Context\Billing\Domain\ValueObject\FrameworkCase;
use App\Context\Billing\Domain\ValueObject\FrameworkNature;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoicingFramework;
use App\Context\Billing\Domain\ValueObject\OperationCategory;

/**
 * Decides the invoicing framework (BT-23) of a document from its lines only —
 * document-level allowances and charges do not count.
 *
 * Goods and services together are framed M by default, pending the
 * accountant's ruling (XP Z12-014, use case 31). A line whose nature is
 * unknown counts for nothing here: readiness refuses it on a professional
 * invoice anyway.
 */
final class InvoicingFrameworkPolicy
{
    /**
     * @param list<InvoiceLine> $lines
     */
    public function decide(array $lines, bool $fullyPaidAtIssue): InvoicingFramework
    {
        $goods    = false;
        $services = false;

        foreach ($lines as $line) {
            $goods    = $goods || OperationCategory::GOODS === $line->operationCategory();
            $services = $services || OperationCategory::SERVICES === $line->operationCategory();
        }

        $nature = match (true) {
            $goods && $services => FrameworkNature::MIXED,
            $goods              => FrameworkNature::GOODS,
            default             => FrameworkNature::SERVICES,
        };

        return InvoicingFramework::of($nature, $fullyPaidAtIssue ? FrameworkCase::ALREADY_PAID : FrameworkCase::TO_PAY);
    }
}
