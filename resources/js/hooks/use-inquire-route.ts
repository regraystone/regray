import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { inquire as inquiryInquire } from '@/routes/inquiry';
import { inquire as quoteInquire } from '@/routes/quote';
import { store as quoteInquireStore } from '@/routes/quote/inquire';

/**
 * The inquiry stepper's route for this user, or null if they may not open it.
 *
 * The stepper hangs off whichever list the user reads their inquiries on, so a
 * client, who reads them on Quote, gets `/quote/inquire` and everyone else
 * gets `/inquiry/inquire`. Each of those routes is guarded by the list's own
 * permission as well as by `inquiries.create`, so the two cannot be swapped; the
 * fallback is only safe because `inquiries.create` is granted alongside one of
 * the two `view` permissions and never on its own.
 *
 * Settling that in one place is what keeps the entry points, the sidebar
 * highlight and the breadcrumb agreeing, and the null is the
 * `inquiries.create` check every entry point needs anyway.
 */
export function useInquireRoute(): typeof quoteInquire | null {
    const { can } = usePermissions();

    if (!can(permissions.inquiriesCreate)) {
        return null;
    }

    return can(permissions.quotationsView) ? quoteInquire : inquiryInquire;
}

/**
 * Where a client's stepper posts their own inquiry, or null for anyone else.
 *
 * The twin of `useInquireRoute` for the client's side: a form opened under
 * `/quote/inquire` is sent to `/quote/inquire`. Staff never send one for
 * themselves: their stepper posts under the lead they picked
 * (`leads.inquiries.store`), so they get null here.
 */
export function useInquireStoreUrl(): string | null {
    const { can } = usePermissions();

    if (!can(permissions.inquiriesCreate) || !can(permissions.quotationsView)) {
        return null;
    }

    return quoteInquireStore.url();
}
