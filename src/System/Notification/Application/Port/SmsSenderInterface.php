<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Port;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\System\Notification\Application\Exception\DeliveryRefusedException;

/**
 * Hands one SMS to the provider, synchronously.
 */
interface SmsSenderInterface
{
    /**
     * @throws DeliveryRefusedException   refused for good: no retry
     * @throws ExternalServiceUnavailable 408, 429 (Retry-After kept), 5xx or no answer: retry later
     *
     * @return string the provider message id
     */
    public function send(OutgoingSms $sms): string;
}
