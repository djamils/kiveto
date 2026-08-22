<?php

declare(strict_types=1);

namespace App\Shared\Application\Pdf;

final class PdfRenderingFailedException extends \RuntimeException
{
    public static function unreachable(string $reason, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('The PDF renderer could not be reached: %s', $reason), 0, $previous);
    }

    public static function rejected(int $statusCode, string $body): self
    {
        return new self(\sprintf(
            'The PDF renderer refused the document (HTTP %d): %s',
            $statusCode,
            '' === trim($body) ? 'no detail given' : mb_substr(trim($body), 0, 500),
        ));
    }
}
