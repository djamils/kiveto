<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\SettleCreditNote;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Puts a credit note where its invoice allows — waiting, internal or sent.
 */
final readonly class SettleCreditNote implements CommandInterface
{
    public function __construct(public string $transmissionId)
    {
    }
}
