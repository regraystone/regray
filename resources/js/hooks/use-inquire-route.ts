import { usePermissions } from '@/hooks/use-permissions';
import { inquire as inquiryInquire } from '@/routes/inquiry';
import { inquire as quoteInquire } from '@/routes/quote';

/**
 * The inquiry stepper's route for this user, or null if they may not open it.
 *
 * The stepper hangs off whichever list the user reads their inquiries on, so a
 * client, who reads them on Quote, gets `/quote/inquire` and everyone else
 * gets `/inquiry/inquire`. Each of those routes is guarded by the list's own
 * permission as well as by `add inquiry`, so the two cannot be swapped; the
 * fallback is only safe because `add inquiry` is granted alongside one of the
 * two `view` permissions and never on its own.
 *
 * Settling that in one place is what keeps the entry points, the sidebar
 * highlight and the breadcrumb agreeing, and the null is the `add inquiry`
 * check every entry point needs anyway.
 */
export function useInquireRoute(): typeof quoteInquire | null {
    const { can } = usePermissions();

    if (!can('add inquiry')) {
        return null;
    }

    return can('view quotation') ? quoteInquire : inquiryInquire;
}
