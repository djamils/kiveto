<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Keeps secrets carried by URLs out of the logs: the `token` query parameter
 * (Brevo webhooks) and the token path segment of the password-reset and
 * invitation links become `***` in the message, the context and the extra
 * fields (request URI, URL) and in the messages of the logged exceptions
 * (masked in place: an exception cannot be cloned, and the formatters read
 * the message from the object). Nginx applies the same masking to its access
 * log.
 */
#[AsMonologProcessor]
final class WebhookTokenMaskingProcessor implements ProcessorInterface
{
    private const array PATTERNS = [
        '/([?&]token=)[^&\s"\'#]+/'                       => '$1***',
        '#(/(?:reinitialiser|invitation)/)[^/?\s"\'\#]+#' => '$1***',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: self::mask($record->message),
            context: self::maskAll($record->context),
            extra: self::maskAll($record->extra),
        );
    }

    public static function mask(string $value): string
    {
        return (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $value);
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    private static function maskAll(array $values): array
    {
        foreach ($values as $key => $value) {
            if (\is_string($value)) {
                $values[$key] = self::mask($value);
            } elseif (\is_array($value)) {
                $values[$key] = self::maskAll($value);
            } elseif ($value instanceof \Throwable) {
                self::maskThrowable($value);
            }
        }

        return $values;
    }

    private static function maskThrowable(\Throwable $throwable): void
    {
        for ($current = $throwable; null !== $current; $current = $current->getPrevious()) {
            $masked = self::mask($current->getMessage());
            if ($masked !== $current->getMessage()) {
                new \ReflectionProperty($current instanceof \Exception ? \Exception::class : \Error::class, 'message')->setValue($current, $masked);
            }
        }
    }
}
