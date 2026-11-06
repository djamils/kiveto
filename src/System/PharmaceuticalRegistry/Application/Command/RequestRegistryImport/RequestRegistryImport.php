<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Command\RequestRegistryImport;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Lancer l'import » (G1): the files waiting in the source's current folder
 * are imported by a worker (routed to the `async` transport), then archived
 * and recorded with who asked for it. Nothing happens when they are not
 * there, or when they are the very files of the last import.
 */
final readonly class RequestRegistryImport implements CommandInterface
{
    public function __construct(
        public string $source = 'ANMV',
        public ?string $requestedBy = null,
    ) {
    }
}
