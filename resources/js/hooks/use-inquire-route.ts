import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { inquire as inquiryInquire } from '@/routes/inquiry';
import { store as inquiryInquireStore } from '@/routes/inquiry/inquire';
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
 * Where the stepper posts, for this user, or null if they may not open it.
 *
 * The twin of `useInquireRoute`: the same permission picks the same side, so a
 * form opened under `/quote/inquire` is sent to `/quote/inquire`. The two POST
 * routes carry the same guards as their GET pages.
 */
export function useInquireStoreUrl(): string | null {
    const { can } = usePermissions();

    if (!can(permissions.inquiriesCreate)) {
        return null;
    }

    return can(permissions.quotationsView)
        ? quoteInquireStore.url()
        : inquiryInquireStore.url();
}
