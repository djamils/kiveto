<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Logging\WebhookTokenMaskingProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * AC 11c: webhook secrets and reset / invitation tokens never reach the logs.
 */
final class WebhookTokenMaskingProcessorTest extends TestCase
{
    public function testUrlsAreMasked(): void
    {
        self::assertSame('/webhook/brevo_mailer?token=***', WebhookTokenMaskingProcessor::mask('/webhook/brevo_mailer?token=s3cr3t'));
        self::assertSame('/webhook/brevo_sms?a=1&token=***&b=2', WebhookTokenMaskingProcessor::mask('/webhook/brevo_sms?a=1&token=s3cr3t&b=2'));
        self::assertSame('http://clinic.kiveto.local/reinitialiser/***', WebhookTokenMaskingProcessor::mask('http://clinic.kiveto.local/reinitialiser/Ab3dEf'));
        self::assertSame('/invitation/***?x=1', WebhookTokenMaskingProcessor::mask('/invitation/Ab3dEf?x=1'));
        self::assertSame('/webhook/brevo_sms?mytoken=kept', WebhookTokenMaskingProcessor::mask('/webhook/brevo_sms?mytoken=kept'));
        self::assertSame('/clients/42', WebhookTokenMaskingProcessor::mask('/clients/42'));
    }

    public function testTheMessageContextAndExtraAreMasked(): void
    {
        $record = new LogRecord(
            new \DateTimeImmutable('2026-10-26 10:42:00'),
            'request',
            Level::Error,
            'Uncaught PHP Exception NotFoundHttpException: "No route found for "GET http://hooks.kiveto.app/webhook/x?token=s3cr3t""',
            ['request_uri' => 'http://clinic.kiveto.local/invitation/Ab3dEf', 'route_parameters' => ['_route' => 'reset', 'path' => '/reinitialiser/Ab3dEf'], 'attempt' => 2],
            ['url'         => '/webhook/brevo_mailer?token=s3cr3t'],
        );

        $masked = new WebhookTokenMaskingProcessor()($record);

        self::assertSame('Uncaught PHP Exception NotFoundHttpException: "No route found for "GET http://hooks.kiveto.app/webhook/x?token=***""', $masked->message);
        self::assertSame(['request_uri' => 'http://clinic.kiveto.local/invitation/***', 'route_parameters' => ['_route' => 'reset', 'path' => '/reinitialiser/***'], 'attempt' => 2], $masked->context);
        self::assertSame(['url' => '/webhook/brevo_mailer?token=***'], $masked->extra);
    }

    public function testLoggedExceptionMessagesAreMasked(): void
    {
        $error     = new \TypeError('Bad /invitation/Ab3dEf');
        $exception = new \RuntimeException('No route found for "GET /webhook/x?token=s3cr3t"', previous: $error);
        $plain     = new \LogicException('Nothing secret');

        new WebhookTokenMaskingProcessor()(new LogRecord(new \DateTimeImmutable(), 'request', Level::Error, 'Uncaught', ['exception' => $exception, 'other' => $plain]));

        self::assertSame('No route found for "GET /webhook/x?token=***"', $exception->getMessage());
        self::assertSame('Bad /invitation/***', $error->getMessage());
        self::assertSame('Nothing secret', $plain->getMessage());
    }
}
