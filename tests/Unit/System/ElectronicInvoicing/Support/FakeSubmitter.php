<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Support;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\System\ElectronicInvoicing\Domain\Port\FlowSubmitterInterface;
use App\System\ElectronicInvoicing\Domain\Port\OutboundFlow;
use App\System\ElectronicInvoicing\Domain\Port\PlatformRefusedFlowException;
use App\System\ElectronicInvoicing\Domain\Port\SubmissionReceipt;

final class FakeSubmitter implements FlowSubmitterInterface
{
    /** @var list<OutboundFlow> */
    public array $flows = [];

    public function __construct(private readonly bool $refuse = false, private readonly bool $down = false)
    {
    }

    public function submit(OutboundFlow $flow): SubmissionReceipt
    {
        if ($this->refuse) {
            throw new PlatformRefusedFlowException('Invalid recipient');
        }

        if ($this->down) {
            throw ExternalServiceUnavailable::unreachable('platform');
        }

        $this->flows[] = $flow;

        return new SubmissionReceipt('flow-' . $flow->trackingId, new \DateTimeImmutable('2026-10-21 10:00:01'));
    }
}
