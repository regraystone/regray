import { useCallback, useEffect, useMemo, useRef } from 'react';
import { useWatch, type UseFormReturn } from 'react-hook-form';
import {
    clearInquiryDraft,
    inquiryDraftState,
    writeInquiryDraft,
} from '@/lib/inquiry-draft-storage';
import type {
    InquiryDraftInput,
    InquiryDraftValues,
    InquiryFormInput,
    InquiryFormValues,
} from '@/lib/inquiry-schema';

/**
 * How long after the last change the draft is written.
 *
 * Long enough that a reader typing a note is not having the storage hit on
 * every keystroke, short enough that closing the tab right after answering a
 * question still finds the answer on the way back.
 */
export const DRAFT_AUTOSAVE_MS = 2000;

type InquiryDraftAutosaveParams = {
    userId: number;
    /**
     * Whether the restore has run.
     *
     * The autosave stays inert until it has, because the first thing either
     * form holds is the blank default: writing that out on the way in would
     * replace the stored draft with nothing before the reader had seen it.
     */
    isRestored: boolean;
    step: number;
    editingProjectId: string | null;
    methods: UseFormReturn<InquiryFormInput, unknown, InquiryFormValues>;
    draftMethods: UseFormReturn<InquiryDraftInput, unknown, InquiryDraftValues>;
};

/**
 * Keep the stepper's working state in this browser, a couple of seconds after
 * the reader stops changing it.
 *
 * One write for both forms plus the step and the project being edited, because
 * all four are one draft: a refresh has to bring back where they were, not half
 * of it. The serialized state is what decides whether anything is worth
 * writing, so a re-render that changed nothing costs no storage and a step
 * change is caught on its way out rather than two seconds late.
 */
export function useInquiryDraftAutosave({
    userId,
    isRestored,
    step,
    editingProjectId,
    methods,
    draftMethods,
}: InquiryDraftAutosaveParams): { discardDraft: () => void } {
    const projects = useWatch({
        control: methods.control,
        name: 'projects',
    });

    /*
     * The whole draft in one subscription.
     *
     * A whole-form watch is typed as partial, because a watcher cannot know
     * that every key was handed a default — but this form's always are, and
     * a draft is only worth saving whole, so the value that arrives is the
     * full form the cast says it is.
     */
    const draft = useWatch({
        control: draftMethods.control,
    }) as InquiryDraftInput;

    const state = useMemo(
        () =>
            inquiryDraftState({
                step,
                editingProjectId,
                projects,
                draft,
            }),
        [step, editingProjectId, projects, draft],
    );

    const serialized = useMemo(() => JSON.stringify(state), [state]);

    /*
     * What the flushes see. Kept in refs and refreshed from an effect rather
     * than written during render, so the listeners and timers registered on
     * mount keep reading the state of the latest commit rather than of the one
     * they were created in.
     */
    const stateRef = useRef(state);
    const serializedRef = useRef(serialized);

    /** The last state that storage confirmed it took. */
    const lastWrittenRef = useRef<string | null>(null);
    const timerRef = useRef<number | null>(null);

    useEffect(() => {
        stateRef.current = state;
        serializedRef.current = serialized;
    }, [state, serialized]);

    const cancelPending = useCallback(() => {
        if (timerRef.current !== null) {
            window.clearTimeout(timerRef.current);
            timerRef.current = null;
        }
    }, []);

    /**
     * Write what is current now, cancelling anything still waiting.
     *
     * Reads the refs rather than the values of the render it was made in, so
     * the same function serves the debounce, the step change, the tab going
     * away and the unmount without any of them needing the freshest closure.
     * A write that storage refused leaves the last known good write standing,
     * so the next change offers itself again rather than being passed over as
     * already saved.
     */
    const writeNow = useCallback(() => {
        cancelPending();

        const current = serializedRef.current;

        if (current === lastWrittenRef.current) {
            return;
        }

        if (writeInquiryDraft(userId, stateRef.current)) {
            lastWrittenRef.current = current;
        }
    }, [userId, cancelPending]);

    // The trailing debounce: every change moves the write out by the full
    // delay, so the draft lands once the reader has stopped rather than once
    // per keystroke along the way.
    useEffect(() => {
        if (!isRestored || serialized === lastWrittenRef.current) {
            return;
        }

        const timer = window.setTimeout(writeNow, DRAFT_AUTOSAVE_MS);

        timerRef.current = timer;

        return () => {
            window.clearTimeout(timer);

            if (timerRef.current === timer) {
                timerRef.current = null;
            }
        };
    }, [isRestored, serialized, writeNow]);

    /*
     * A step change is a decision to move on, so it is written at once: the
     * reader may close the tab the moment the next step appears, and a draft
     * that still says "Services" under a reader standing on Review would put
     * them back one step on the way in.
     *
     * The restore's own step is recorded rather than flushed. The first thing
     * this hook sees after restore is that landing, and writing it immediately
     * would re-stamp a draft the reader has not touched yet.
     */
    const isTrackingStepRef = useRef(false);
    const previousStepRef = useRef(step);

    useEffect(() => {
        if (!isRestored) {
            return;
        }

        if (!isTrackingStepRef.current) {
            isTrackingStepRef.current = true;
            previousStepRef.current = step;

            return;
        }

        if (previousStepRef.current === step) {
            return;
        }

        previousStepRef.current = step;
        writeNow();
    }, [isRestored, step, writeNow]);

    /*
     * The two moments a tab can be taken away without another render: the page
     * going into the background, and it being unloaded. Both flush the pending
     * write there and then, because after either of them there may be no next
     * render to schedule one on.
     */
    useEffect(() => {
        if (!isRestored) {
            return;
        }

        const onVisibilityChange = () => {
            if (document.visibilityState === 'hidden') {
                writeNow();
            }
        };

        const onPageHide = () => writeNow();

        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('pagehide', onPageHide);

        return () => {
            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            );
            window.removeEventListener('pagehide', onPageHide);
            // Unmount: whatever is still waiting goes out with the page.
            writeNow();
        };
    }, [isRestored, writeNow]);

    /**
     * Drop the draft for good, taking any pending write with it.
     *
     * Called once an inquiry has been sent. The cancellation is the point: the
     * timer that was waiting to save the request that has just gone out would
     * otherwise be free to put it back a moment after it was cleared.
     */
    const discardDraft = useCallback(() => {
        cancelPending();
        lastWrittenRef.current = null;
        clearInquiryDraft(userId);
    }, [userId, cancelPending]);

    return { discardDraft };
}
