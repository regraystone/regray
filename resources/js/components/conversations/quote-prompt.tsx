import { Link } from '@inertiajs/react';
import { FileText, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { QuotePromptState } from '@/hooks/use-quote-prompt';

/**
 * The floating pill that opens the inquiry stepper from a client's Regray Team
 * thread. The thread keeps it in the row stuck to the bottom of the messages.
 */
const QuotePrompt = ({ label, href, onDismiss }: QuotePromptState) => (
    <div
        className="pointer-events-auto flex shrink-0 rounded-full shadow-md"
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
