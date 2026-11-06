<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\SearchAccounts;

use App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchAccountsHandler
{
    public function __construct(
        private UserReadRepositoryInterface $users,
    ) {
    }

    public function __invoke(SearchAccounts $query): AccountPage
    {
        $type    = UserType::from($query->type);
        $text    = null !== $query->text && '' !== trim($query->text) ? trim($query->text) : null;
        $perPage = max(1, $query->perPage);
        $total   = $this->users->countAccounts($type, $text);
        $page    = min(max(1, $query->page), max(1, (int) ceil($total / $perPage)));

        return new AccountPage(
            items: 0 === $total ? [] : $this->users->accounts($type, $text, ($page - 1) * $perPage, $perPage),
            total: $total,
            page: $page,
            perPage: $perPage,
        );
    }
}
