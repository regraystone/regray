<?php

namespace App\Data\Message;

final readonly class ConversationListFilters
{
    public const FILTER_ALL = 'all';

    public const FILTER_UNREAD = 'unread';

    public function __construct(
        public ?string $search = null,
        public string $filter = self::FILTER_ALL,
        public int $page = 1,
    ) {}

    public function onlyUnread(): bool
    {
        return $this->filter === self::FILTER_UNREAD;
    }
}
