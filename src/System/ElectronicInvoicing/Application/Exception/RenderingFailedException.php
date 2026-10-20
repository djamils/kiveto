<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Exception;

/**
 * The document could not be rendered into a valid file — always our bug.
 */
final class RenderingFailedException extends \RuntimeException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The document did not render into a valid file: ' . implode(' | ', $errors));
    }
}
