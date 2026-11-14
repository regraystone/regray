import type { SocialNotice as Notice } from '@/lib/auth-schema';

/**
 * Why a Google sign-in came back to this page: cancelled, timed out, or an
 * email Google has not verified. Shown once, above the form.
 */
const SocialNotice = ({ notice }: { notice?: Notice | null }) =>
    notice ? (
        <p
            role={notice.type === 'error' ? 'alert' : 'status'}
            className="mb-6 border border-border bg-muted/30 p-3 text-center text-[0.85rem] font-medium text-secondary-foreground max-md:mb-(--auth-block-gap)"
            data-test="social-notice"
        >
            {notice.message}
        </p>
    ) : null;

export default SocialNotice;
