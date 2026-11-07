<?php

namespace App\Services;

use App\Data\ClientInquiryCard;
use App\Data\InquiryListFilters;
use App\Models\Inquiry;
use App\Models\Stone;
use App\Models\User;
use App\Policies\InquiryActions;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientInquiryService
{
    public const PER_PAGE = 10;

    public function __construct(
        public readonly InquiryRepositoryInterface $inquiryRepository,
        public readonly InquiryActions $inquiryActions,
    ) {}

    /**
     * One page of the client's own inquiries, as cards.
     *
     * The stones of the whole page are read in one query, then dealt out to their
     * inquiry, so the cost of a page does not grow with the number of cards on it.
     *
     * @return array{inquiries: LengthAwarePaginator<int, ClientInquiryCard>, hasMore: bool}
     */
    public function listFor(User $client, InquiryListFilters $filters): array
    {
        $page = $this->inquiryRepository->paginateForClient(
            $client,
            $filters,
            $this->escapeSearch($filters->search),
            self::PER_PAGE,
        );

        $ids = array_values($page->getCollection()->map(fn (Inquiry $inquiry): int => $inquiry->id)->all());

        $stones = $this->inquiryRepository->distinctStonesFor($ids)->groupBy('inquiry_id');

        $cards = $page->through(fn (Inquiry $inquiry): ClientInquiryCard => new ClientInquiryCard(
            reference: $inquiry->reference,
            status: $inquiry->status,
            paymentStatus: $inquiry->payment_status,
            createdAt: $inquiry->created_at,
            title: $inquiry->name,
            stones: array_values(($stones->get($inquiry->id) ?? collect())
                ->map(fn (Stone $stone): array => [
                    'slug' => $stone->slug,
                    'name' => $stone->name,
                    'thumbnail_path' => $stone->thumbnail_path,
                ])->all()),
            services: $inquiry->service === null ? [] : [[
                'id' => $inquiry->service->slug,
                'name' => $inquiry->service->name,
            ]],
            actions: $this->inquiryActions->for($inquiry),
        ));

        return ['inquiries' => $cards, 'hasMore' => $cards->hasMorePages()];
    }

    /**
     * A search term made safe to put inside a LIKE pattern: `%` and `_` are the
     * reader's characters, not wildcards. The same escaping the stone search uses.
     */
    public function escapeSearch(?string $search): ?string
    {
        $search = $search === null ? '' : trim($search);

        return $search === '' ? null : str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
    }
}
