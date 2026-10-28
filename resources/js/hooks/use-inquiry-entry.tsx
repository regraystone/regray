import { router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import InquiryDraftPrompt from '@/components/inquiry-draft-prompt';
import StaffInquireDialog from '@/components/staff-inquire-dialog';
import { useInquireRoute } from '@/hooks/use-inquire-route';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import type { StaffInquireKind } from '@/lib/staff-inquire';
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
 * Staff are asked first who the inquiry is for (StaffInquireDialog: Platform or
 * Direct), and the stepper opens on that branch (`?kind=`). A client, who sends
 * their own, is never asked.
 *
 * The buttons keep their own look and their own permission checks — this hook
 * decides where a click goes, not whether it is offered.
 */
export function useInquiryEntry(): InquiryEntry {
    const inquire = useInquireRoute();
    const { can } = usePermissions();
    const isStaff = !can(permissions.quotationsView);
    const userId = usePage<{ auth: Auth }>().props.auth.user.id;
    const [savedAt, setSavedAt] = useState<string | null>(null);
    const [isAskingKind, setIsAskingKind] = useState(false);
    const [kind, setKind] = useState<StaffInquireKind | null>(null);

    const closePrompt = () => setSavedAt(null);

    const visitStepper = (chosen: StaffInquireKind | null = kind) => {
        if (inquire !== null) {
            router.visit(
                inquire.url(chosen === null ? {} : { query: { kind: chosen } }),
            );
        }
    };

    const openStepper = (chosen: StaffInquireKind | null) => {
        const draft = readInquiryDraft(userId);

        if (draft === null) {
            visitStepper(chosen);

            return;
        }

        setKind(chosen);
        setSavedAt(draft.savedAt);
    };

    const onInquireClick = () => {
        if (inquire === null) {
            return;
        }

        if (isStaff) {
            setIsAskingKind(true);

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
        isPromptOpen: savedAt !== null || isAskingKind,
        onInquireClick,
        draftPrompt: (
            <>
                <StaffInquireDialog
                    open={isAskingKind}
                    onOpenChange={setIsAskingKind}
                    onChoose={(chosen) => {
                        setIsAskingKind(false);
                        openStepper(chosen);
                    }}
                />
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
            </>
        ),
    };
}
