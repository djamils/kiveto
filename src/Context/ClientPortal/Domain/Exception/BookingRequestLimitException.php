<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** D14: one pending new-client request per clinic and three in all per account. */
final class BookingRequestLimitException extends \DomainException
{
    public const string CLINIC  = 'clinic';
    public const string OVERALL = 'overall';

    /**
     * @param string $limit clinic|overall
     */
    public function __construct(public readonly string $limit)
    {
        parent::__construct(\sprintf('Too many pending booking requests (%s).', $limit));
    }
}
