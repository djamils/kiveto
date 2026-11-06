<?php

declare(strict_types=1);

namespace App\System\Audit\Domain\ValueObject;

/**
 * The machine name of what happened, dotted (`clinic.created`,
 * `support.session_started`): shown under the summary and searchable.
 */
final class AuditAction
{
    private const string PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/';

    private const int MAX_LENGTH = 64;

    private string $code;

    private function __construct()
    {
    }

    public static function fromString(string $code): self
    {
        if (\strlen($code) > self::MAX_LENGTH || 1 !== preg_match(self::PATTERN, $code)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not an audit action code.', $code));
        }

        $action       = new self();
        $action->code = $code;

        return $action;
    }

    public function toString(): string
    {
        return $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
