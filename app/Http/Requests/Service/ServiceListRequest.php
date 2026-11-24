<?php

namespace App\Http\Requests\Service;

use App\Http\Requests\DataTableRequest;

/**
 * The All services page's query: the DataTable's search and page, and its two
 * filters. `type` is a service type's slug, or `all`; `status` is `active`,
 * `inactive` or `all`. A value it doesn't know is `all`, as a DataTable's bad
 * value falls back to its default.
 */
class ServiceListRequest extends DataTableRequest
{
    public const STATUSES = ['active', 'inactive'];

    /** The service type's slug, or null for every type. */
    public function type(): ?string
    {
        $type = $this->query('type');

        return is_string($type) && $type !== '' && $type !== 'all' && strlen($type) <= 100 ? $type : null;
    }

    /** True for the visible, false for the hidden, null for both. */
    public function active(): ?bool
    {
        $status = $this->query('status');

        return is_string($status) && in_array($status, self::STATUSES, true) ? $status === 'active' : null;
    }

    /**
     * The filters as the page holds them.
     *
     * @return array{search: string, page: int, perPage: int, type: string, status: string}
     */
    public function serviceFilters(): array
    {
        $active = $this->active();

        return [
            ...$this->filters(),
            'type' => $this->type() ?? 'all',
            'status' => $active === null ? 'all' : ($active ? 'active' : 'inactive'),
        ];
    }
}
