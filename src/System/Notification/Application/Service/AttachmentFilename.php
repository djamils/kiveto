<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Service;

use function Symfony\Component\String\u;

/**
 * The name an attachment is sent under: ASCII letters, digits, dot, dash and
 * underscore only (document names may carry accents and spaces — « Compte
 * rendu – Milo.pdf »), so that no header encoding or provider check trips on it.
 */
final class AttachmentFilename
{
    public const string FALLBACK = 'piece-jointe';

    public static function safe(string $filename): string
    {
        $ascii = u($filename)->ascii()->toString();
        $safe  = mb_trim(preg_replace(['/[^A-Za-z0-9._-]+/', '/-{2,}/'], '-', $ascii) ?? '', '-.');

        return '' !== $safe ? $safe : self::FALLBACK;
    }
}
