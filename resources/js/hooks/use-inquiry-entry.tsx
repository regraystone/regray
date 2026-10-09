import { router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import InquiryDraftPrompt from '@/components/inquiry-draft-prompt';
import { useInquireRoute } from '@/hooks/use-inquire-route';
import {
    clearInquiryDraft,
    readInquiryDraft,
} from '@/lib/inquiry-draft-storage';
import type { Auth } from '@/types';

type InquiryEntry = {
    /** The stepper's route, or null when this user may not open it. */
    inquire: ReturnType<typeof useInquireRoute>;
    /** Whether the draft prompt is up, for anything that hides behind it. */
    isPromptOpen: boolean;
    /** The click every inquire button shares. */
    onInquireClick: () => void;
    /** The prompt itself, rendered once wherever the buttons are. */
    draftPrompt: ReactNode;
};

/**
 * How an inquire button opens the stepper, in one place.
 *
 * The draft is read at the moment of the click rather than held in state,
 * because what decides the next screen is whether there is a draft *then*: one
 * raised on another page, cleared on a visit that never made a change, or
 * written by the autosave a second ago. No draft goes straight through with no
 * question; a draft asks whether to continue it or start over, and starting
 * over forgets it before the stepper is opened so the blank form it shows
 * cannot be talked out of being blank on a refresh.
 *
 * The buttons keep their own look and their own permission checks — this hook
 * decides where a click goes, not whether it is offered.
 */
export function useInquiryEntry(): InquiryEntry {
    const inquire = useInquireRoute();
    const userId = usePage<{ auth: Auth }>().props.auth.user.id;
    const [savedAt, setSavedAt] = useState<string | null>(null);

    const closePrompt = () => setSavedAt(null);

    const visitStepper = () => {
        if (inquire !== null) {
            router.visit(inquire.url());
        }
    };

    const onInquireClick = () => {
        if (inquire === null) {
            return;
        }

        const draft = readInquiryDraft(userId);

        if (draft === null) {
            visitStepper();

            return;
        }

        setSavedAt(draft.savedAt);
    };

    return {
        inquire,
        isPromptOpen: savedAt !== null,
        onInquireClick,
        draftPrompt: (
            <InquiryDraftPrompt
                open={savedAt !== null}
                savedAt={savedAt}
                onOpenChange={(open) => {
                    if (!open) {
                        closePrompt();
                    }
                }}
                onContinue={() => {
                    closePrompt();
                    visitStepper();
                }}
                onStartNew={() => {
                    clearInquiryDraft(userId);
                    closePrompt();
                    visitStepper();
                }}
            />
        ),
    };
}
