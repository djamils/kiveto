<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Gathers what the Portal holds about an account into one ZIP archive. */
interface DataExportBuilderInterface
{
    /**
     * @throws \Throwable when a source or the PDF service fails
     *
     * @return string the ZIP content
     */
    public function build(string $userId): string;
}
