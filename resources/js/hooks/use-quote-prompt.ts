import { useMemo, useState } from 'react';
import { useInquireRoute } from '@/hooks/use-inquire-route';
import { readInquiryDraft } from '@/lib/inquiry-draft-storage';
import {
    dismissQuotePrompt,
    isQuotePromptDismissed,
    quotePromptView,
} from '@/lib/quote-prompt';

export type QuotePromptState = {
    label: string;
    href: string;
    onDismiss: () => void;
};

/**
 * The "Request a quotation" pill for one thread, or null when it stays hidden.
 *
 * The draft is only read once the server has said yes, so a thread that never
 * offers the pill never touches the stepper's storage.
 */
export function useQuotePrompt(
    canRequestQuote: boolean,
    userId: number,
): QuotePromptState | null {
    const inquireRoute = useInquireRoute();
    const [isDismissed, setIsDismissed] = useState(() =>
        isQuotePromptDismissed(),
    );
    const hasDraft = useMemo(
        () => canRequestQuote && readInquiryDraft(userId) !== null,
        [canRequestQuote, userId],
    );

    const { isVisible, label } = quotePromptView({
        canRequestQuote,
        isDismissed,
        hasDraft,
    });

    if (!isVisible || inquireRoute === null) {
        return null;
    }

    return {
        label,
        href: inquireRoute.url(),
        onDismiss: () => {
            dismissQuotePrompt();
            setIsDismissed(true);
        },
    };
}
