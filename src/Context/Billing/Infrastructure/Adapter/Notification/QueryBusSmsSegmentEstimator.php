<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Notification;

use App\Context\Billing\Application\Port\InvoiceMessage;
use App\Context\Billing\Application\Port\SmsSegmentEstimatorInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Notification\Application\Query\PreviewSmsSegments\PreviewSmsSegments;

/**
 * Notification's PreviewSmsSegments: the reminder SMS rendered as the clinic
 * would send it (its name and phone are part of the text).
 */
final readonly class QueryBusSmsSegmentEstimator implements SmsSegmentEstimatorInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function reminderSegments(string $clinicId, array $parameters): int
    {
        $segments = $this->queryBus->ask(new PreviewSmsSegments($clinicId, InvoiceMessage::REMINDER, $parameters));

        return \is_int($segments) ? $segments : 0;
    }
}
