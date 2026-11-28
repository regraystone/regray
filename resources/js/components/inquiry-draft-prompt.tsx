import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * When a stored draft is quoted back to the reader, as it is written on the
 * rest of the app's timestamps: a date, then the time of day, both local.
 */
const savedAtFormatter = new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
});

/**
 * The moment a draft claims it was saved, or null if it cannot say.
 *
 * Checked rather than formatted straight off the string: the value is read out
 * of storage, and a record that survived parsing with something that is not a
 * date in it should cost the reader the sentence, not the dialog.
 */
const formatSavedAt = (savedAt: string | null): string | null => {
    if (savedAt === null) {
        return null;
    }

    const date = new Date(savedAt);

    return Number.isNaN(date.getTime()) ? null : savedAtFormatter.format(date);
};

type InquiryDraftPromptProps = {
    open: boolean;
    /** When the draft was saved, as the record in storage has it. */
    savedAt: string | null;
    onOpenChange: (open: boolean) => void;
    /** Carry on with the draft; the stepper restores it on the way in. */
    onContinue: () => void;
    /** Throw the draft away and open the stepper blank. */
    onStartNew: () => void;
};

/**
 * The one question asked before an inquire button opens a stepper that has a
 * draft waiting in it.
 *
 * Asked here rather than on the stepper, because the answer decides whether
 * the stepper is even opened: a reader with no draft goes straight through and
 * never sees this, and one who does gets the choice before anything of theirs
 * is touched. Starting fresh clears the draft first, so the blank stepper that
 * follows cannot restore the one they just declined.
 */
const InquiryDraftPrompt = ({
    open,
    savedAt,
    onOpenChange,
    onContinue,
    onStartNew,
}: InquiryDraftPromptProps) => {
    const savedOn = formatSavedAt(savedAt);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                        Continue your draft?
                    </DialogTitle>
                    <DialogDescription>
                        {savedOn === null
                            ? 'A draft is saved in this browser. Continue where you left off, or start a new inquiry.'
                            : `A draft was saved on ${savedOn}. Continue where you left off, or start a new inquiry.`}
                    </DialogDescription>
                </DialogHeader>
                {/* Side by side on every screen, each label on one line, as Sir Mark asked. */}
                <DialogFooter className="flex-row gap-3 sm:gap-3 [&_button]:flex-1 [&_button]:px-3 [&_button]:whitespace-nowrap sm:[&_button]:flex-none sm:[&_button]:px-5">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onStartNew}
                    >
                        Start new
                    </Button>
                    <Button type="button" onClick={onContinue}>
                        Continue draft
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default InquiryDraftPrompt;
