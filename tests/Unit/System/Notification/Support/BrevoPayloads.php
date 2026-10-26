<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Support;

use Symfony\Component\HttpFoundation\Request;

/**
 * Recorded Brevo webhook payloads (tests/Fixtures/Brevo) and the requests
 * Brevo would send with them.
 */
final class BrevoPayloads
{
    public const string BREVO_IP = '1.179.112.10';
    public const string SECRET   = 'brevo-webhook-secret';

    /**
     * @param 'email'|'sms' $channel
     *
     * @return array<mixed>
     */
    public static function payload(string $channel, string $event): array
    {
        $json = file_get_contents(\dirname(__DIR__, 4) . '/Fixtures/Brevo/' . $channel . '/' . $event . '.json');
        \assert(\is_string($json));
        $payload = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($payload));

        return $payload;
    }

    /**
     * The payload's `ts_event`, as RecordDeliveryEvent carries it (ATOM, UTC).
     *
     * @param array<mixed> $payload
     */
    public static function eventTime(array $payload): string
    {
        \assert(\is_int($payload['ts_event']));

        return new \DateTimeImmutable('@' . $payload['ts_event'])->format(\DATE_ATOM);
    }

    /**
     * @param array<mixed>          $payload
     * @param array<string, string> $query
     */
    public static function request(array $payload, string $ip = self::BREVO_IP, ?string $bearer = self::SECRET, array $query = [], string $method = 'POST'): Request
    {
        $server = ['REMOTE_ADDR' => $ip, 'CONTENT_TYPE' => 'application/json'];
        if (null !== $bearer) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $bearer;
        }

        return Request::create('https://hooks.kiveto.test/webhook/brevo' . ([] === $query ? '' : '?' . http_build_query($query)), $method, server: $server, content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
