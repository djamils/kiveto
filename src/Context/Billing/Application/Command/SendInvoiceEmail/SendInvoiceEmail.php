<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\SendInvoiceEmail;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The e-mail of one logged SEND dispatch, with the invoice PDF. Queued on
 * `async` by SendInvoiceToClient.
 */
final readonly class SendInvoiceEmail implements CommandInterface
{
    public function __construct(public string $dispatchId)
    {
    }
}
