<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Support;

use App\System\Notification\Application\Port\EmailSenderInterface;
use App\System\Notification\Application\Port\OutgoingEmail;
use App\System\Notification\Application\Port\OutgoingSms;
use App\System\Notification\Application\Port\SmsSenderInterface;

/**
 * Remembers what it was handed and answers with a provider id, or throws
 * the failure it was given.
 */
final class FakeSenders implements EmailSenderInterface, SmsSenderInterface
{
    /** @var list<OutgoingEmail> */
    public array $emails = [];

    /** @var list<OutgoingSms> */
    public array $sms = [];

    public ?\Throwable $failure = null;

    public function send(OutgoingEmail|OutgoingSms $message): string
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        if ($message instanceof OutgoingEmail) {
            $this->emails[] = $message;

            return 'brevo-email-' . \count($this->emails);
        }

        $this->sms[] = $message;

        return 'brevo-sms-' . \count($this->sms);
    }
}
