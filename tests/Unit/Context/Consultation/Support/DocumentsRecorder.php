<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Support;

use App\Context\Consultation\Application\Port\DocumentsMessage;

/**
 * What the documents e-mail doubles saw: jobs queued, files rendered,
 * messages requested.
 */
final class DocumentsRecorder
{
    /** @var list<array{string, string, list<string>, string}> */
    public array $queued = [];

    /** @var list<string> */
    public array $rendered = [];

    /** @var list<DocumentsMessage> */
    public array $messages = [];
}
