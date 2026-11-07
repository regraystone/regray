import { Link } from '@inertiajs/react';
import { FileText, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { QuotePromptState } from '@/hooks/use-quote-prompt';

/**
 * The floating pill that opens the inquiry stepper from a client's Regray Team
 * thread. It is the last item in the scrolling messages, stuck to the bottom of
 * their view: that keeps it above the composer however tall that grows, the
 * newest message sits clear above it, and a scroll started on it still scrolls.
 */
const QuotePrompt = ({ label, href, onDismiss }: QuotePromptState) => (
    <div
        className="sticky bottom-0 z-10 mt-auto flex shrink-0 self-center rounded-full shadow-md"
        data-testid="quote-prompt"
    >
        <Button
            asChild
            size="sm"
            className="rounded-l-full rounded-r-none pr-2"
        >
            <Link href={href} aria-label={label}>
                <FileText aria-hidden />
                {label}
            </Link>
        </Button>
        <Button
            type="button"
            size="sm"
            className="rounded-l-none rounded-r-full pr-2.5 pl-2"
            aria-label="Hide the quotation button"
            onClick={onDismiss}
        >
            <X aria-hidden />
        </Button>
    </div>
);

export default QuotePrompt;
