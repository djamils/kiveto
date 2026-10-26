<?php

declare(strict_types=1);

namespace App\Shared\Application\Document;

/**
 * The PDF of a document could not be produced: the rendering service is
 * unreachable or refused it. A gateway failure, not a bug in the request.
 */
final class DocumentRenderingFailed extends \RuntimeException
{
    public static function because(\Throwable $previous): self
    {
        return new self(\sprintf('The document could not be rendered: %s', $previous->getMessage()), 0, $previous);
    }
}
