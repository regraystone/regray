<?php

namespace App\Actions\Profile;

use App\Enums\Permission;
use App\Models\User;
use App\Repositories\Contracts\InquiryRepositoryInterface;

/**
 * What the Profile hub says about the reader beyond the shared props: the
 * phone and company they gave, how many of their quotations are open, and
 * whether they have a default address. A figure is null for a reader who
 * may not open the page it stands for (staff have no Quote list).
 */
class ProfileSummaryAction
{
    public function __construct(
        public readonly InquiryRepositoryInterface $inquiryRepository,
    ) {}

    /**
     * @return array{phone: string|null, company: string|null, openQuotations: int|null, hasDefaultAddress: bool|null}
     */
    public function execute(User $user): array
    {
        $profile = $user->profile;

        return [
            'phone' => $profile?->phone,
            'company' => $profile?->company,
            'openQuotations' => $user->can(Permission::QuotationsView->value)
                ? $this->inquiryRepository->countOpenForClient($user)
                : null,
            'hasDefaultAddress' => $user->can(Permission::ProfileAddressesView->value)
                ? $user->addresses()->where('is_default', true)->exists()
                : null,
        ];
    }
}
