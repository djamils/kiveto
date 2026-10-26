<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Webhook;

use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

/**
 * Brevo transactional SMS event → `SmsEvent` keyed by Brevo's `messageId`.
 *
 * Field names follow Brevo's transactional SMS webhook documentation
 * (`messageId`, `msg_status`, `to`, `ts_event`, `date`, `description`), to be
 * checked against a live event (J1). The status is normalised (lower case,
 * without separators), so `hard_bounce` and `hardBounce` read the same.
 * Final failures are named FAILED, a delivery DELIVERED; any other status
 * keeps its normalised name (the consumer decides what it means).
 */
final class BrevoSmsPayloadConverter implements PayloadConverterInterface
{
    private const array FAILURES = ['hardbounce', 'rejected', 'blocked', 'skip', 'skipped', 'error'];

    /**
     * @param array<mixed> $payload
     */
    public function convert(array $payload): SmsEvent
    {
        $id     = $payload['messageId'] ?? null;
        $status = $payload['msg_status'] ?? null;
        if ((!\is_string($id) && !\is_int($id)) || '' === (string) $id || !\is_string($status)) {
            throw new ParseException('Payload is malformed: messageId and msg_status are required.');
        }

        $status = self::normalisedStatus($status);
        $name   = match (true) {
            'delivered' === $status                  => SmsEvent::DELIVERED,
            \in_array($status, self::FAILURES, true) => SmsEvent::FAILED,
            default                                  => $status,
        };

        $event = new SmsEvent($name, (string) $id, $payload);
        if (\is_string($payload['to'] ?? null) || \is_int($payload['to'] ?? null)) {
            $event->setRecipientPhone((string) $payload['to']);
        }

        // Refused now rather than in the worker: the consumer needs the event time.
        self::occurredAt($payload);

        return $event;
    }

    public static function normalisedStatus(string $status): string
    {
        return strtolower((string) preg_replace('/[^A-Za-z]/', '', $status));
    }

    /**
     * When Brevo says the event happened: `ts_event` (Unix seconds), else
     * `date` (« Y-m-d H:i:s », read in Brevo's French account time zone).
     *
     * @param array<mixed> $payload
     *
     * @throws ParseException when neither is usable
     */
    public static function occurredAt(array $payload): \DateTimeImmutable
    {
        $timestamp = $payload['ts_event'] ?? null;
        if (\is_int($timestamp) || (\is_string($timestamp) && ctype_digit($timestamp))) {
            return new \DateTimeImmutable('@' . $timestamp);
        }

        $date = $payload['date'] ?? null;
        if (\is_string($date) && false !== $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date, new \DateTimeZone('Europe/Paris'))) {
            return $parsed;
        }

        throw new ParseException('Payload is malformed: no usable ts_event or date.');
    }
}
