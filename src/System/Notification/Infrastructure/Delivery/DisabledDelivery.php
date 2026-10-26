<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Delivery;

use App\System\Notification\Application\Exception\DeliveryRefusedException;
use App\System\Notification\Application\Port\EmailSenderInterface;
use App\System\Notification\Application\Port\OutgoingEmail;
use App\System\Notification\Application\Port\OutgoingSms;
use App\System\Notification\Application\Port\SmsSenderInterface;

/**
 * NOTIFICATION_DELIVERY=disabled: nothing leaves the application; every
 * message is recorded FAILED « Envoi désactivé ».
 */
final readonly class DisabledDelivery implements EmailSenderInterface, SmsSenderInterface
{
    public const string REASON = 'Envoi désactivé';

    public function send(OutgoingEmail|OutgoingSms $message): string
    {
        throw new DeliveryRefusedException(self::REASON);
    }
}
