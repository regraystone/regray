import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import FileUpload from '@/components/file-upload';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { InquiryAttachment } from '@/data/inquiries';
import {
    isValidAmount,
    isValidNote,
    NOTE_MAX_LENGTH,
    type ExtraField,
    type FileGroup,
} from '@/lib/status-rules';

export type StatusTarget = { value: string; label: string };

const EXTRA_LABELS: Record<ExtraField, string> = {
    quotedTotal: 'Quoted total (PHP)',
    contractTotal: 'Total contract amount (PHP)',
    amountReceived: 'Amount received (PHP)',
};

/**
 * Moves one status and records why, from a table row.
 *
 * 1. The current status, or "Not started".
 * 2. Where it may move to: the allowed targets, never the current status.
 * 3. A required note.
 * 4. Once a target is chosen, the files it needs (one labelled group per
 *    update, a FileUpload per slot) and the amount it needs, if any.
 *
 * Confirm stays disabled until all of that is valid. The server checks the same
 * things again and has the last word; its errors are shown under the fields.
 * The files and fields go out as `files[{type}][]`, `quotedTotal`,
 * `contractTotal` and `amountReceived`.
 */
const StatusUpdateDialog = ({
    open,
    onOpenChange,
    current,
    targets,
    initialTarget = null,
    fileGroups,
    extraFieldFor,
    submitUrl,
    payload = {},
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The current status as a badge, or null for a stage not started. */
    current: ReactNode | null;
    targets: StatusTarget[];
    initialTarget?: string | null;
    fileGroups: (to: string) => FileGroup[];
    extraFieldFor: (to: string) => ExtraField | null;
    submitUrl: string;
    /** Sent with every request, such as the track. */
    payload?: Record<string, string>;
}) => {
    const [to, setTo] = useState<string | null>(initialTarget);
    const [note, setNote] = useState('');
    const [files, setFiles] = useState<Record<string, InquiryAttachment[]>>({});
    const [amount, setAmount] = useState('');
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (open) {
            setTo(initialTarget);
            setNote('');
            setFiles({});
            setAmount('');
            setErrors({});
        }
    }, [open, initialTarget]);

    const groups = useMemo(
        () => (to === null ? [] : fileGroups(to)),
        [to, fileGroups],
    );
    const extra = to === null ? null : extraFieldFor(to);

    const uploaded = (type: string): File[] =>
        (files[type] ?? []).flatMap((entry) =>
            entry.status === 'done' ? [entry.file] : [],
        );

    /** Ready when its uploads are done, one at most unless `multiple`, and at least one if required. */
    const isSlotReady = (
        type: string,
        multiple: boolean,
        required: boolean,
    ): boolean => {
        const entries = files[type] ?? [];
        const done = uploaded(type).length;

        return (
            (done >= 1 || !required) &&
            done === entries.length &&
            (multiple || done <= 1)
        );
    };

    const canConfirm =
        to !== null &&
        isValidNote(note) &&
        groups.every((group) =>
            group.slots.every((slot) =>
                isSlotReady(slot.type, slot.multiple, slot.required),
            ),
        ) &&
        (extra === null || isValidAmount(amount));

    const confirm = () => {
        if (!canConfirm || to === null) {
            return;
        }

        const body = new FormData();

        Object.entries(payload).forEach(([key, value]) =>
            body.append(key, value),
        );
        body.append('to', to);
        body.append('note', note.trim());

        if (extra !== null) {
            body.append(extra, amount.trim());
        }

        groups.forEach((group) =>
            group.slots.forEach((slot) =>
                uploaded(slot.type).forEach((file) =>
                    body.append(`files[${slot.type}][]`, file),
                ),
            ),
        );

        router.post(submitUrl, body, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onSuccess: () => onOpenChange(false),
            onError: (bag) => setErrors(bag as Record<string, string>),
        });
    };

    const error = (field: string) =>
        errors[field] ? (
            <p className="text-xs text-destructive">{errors[field]}</p>
        ) : null;

    const fileError = (type: string) =>
        error(`files.${type}`) ??
        error(
            Object.keys(errors).find((key) =>
                key.startsWith(`files.${type}.`),
            ) ?? '',
        );

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => !saving && onOpenChange(next)}
        >
            <DialogContent
                className="max-h-[90svh] overflow-y-auto sm:max-w-4xl"
                data-testid="status-update-dialog"
            >
                <DialogHeader>
                    <DialogTitle className="text-2xl font-bold tracking-[-0.03em]">
                        Update Status
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        Choose the new status, say why, and add the files it
                        needs.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-6">
                    {/* Current Status Section */}
                    <div className="space-y-3">
                        <p className="text-sm font-bold tracking-[0.05em] text-secondary-foreground uppercase">
                            Current Status
                        </p>
                        <div className="flex min-h-9 items-center">
                            {current ?? (
                                <span className="text-sm text-secondary-foreground">
                                    Not started
                                </span>
                            )}
                        </div>
                    </div>

                    {/* Move To Section in Grid Cols 4 with Borders */}
                    <div className="space-y-3">
                        <p
                            className="text-sm font-bold tracking-[0.05em] text-secondary-foreground uppercase"
                            id="status-move-to"
                        >
                            Move to
                        </p>
                        {targets.length === 0 ? (
                            <p className="text-sm text-secondary-foreground">
                                This status cannot move any further.
                            </p>
                        ) : (
                            <RadioGroup
                                value={to ?? ''}
                                onValueChange={setTo}
                                aria-labelledby="status-move-to"
                            >
                                <div className="grid grid-cols-4 gap-3">
                                    {targets.map((target) => (
                                        <label
                                            key={target.value}
                                            className="flex cursor-pointer items-center gap-2 rounded border-2 border-sidebar-border p-3 transition-all hover:border-foreground hover:bg-muted/50"
                                        >
                                            <RadioGroupItem
                                                value={target.value}
                                            />
                                            <span className="text-xs font-medium">
                                                {target.label}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </RadioGroup>
                        )}
                        {error('to')}
                    </div>

                    {/* Note Section */}
                    <div className="space-y-3 border-t border-sidebar-border pt-6">
                        <Label
                            htmlFor="status-note"
                            className="text-sm font-bold tracking-[0.05em] uppercase"
                        >
                            Note
                        </Label>
                        <Textarea
                            id="status-note"
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            maxLength={NOTE_MAX_LENGTH}
                            rows={4}
                            className="resize-none rounded-none"
                            placeholder="Explain why you're making this status change..."
                        />
                        <p className="text-xs text-secondary-foreground">
                            {note.length} / {NOTE_MAX_LENGTH} characters
                        </p>
                        {error('note')}
                    </div>

                    {/* File Groups Section - Column layout, no group titles */}
                    {groups.length > 0 && (
                        <div className="space-y-4 border-t border-sidebar-border pt-6">
                            {groups.map((group) => (
                                <section
                                    key={group.label}
                                    className="space-y-3"
                                    aria-label={group.label}
                                >
                                    <div className="grid gap-4">
                                        {group.slots.map((slot) => (
                                            <div
                                                key={slot.type}
                                                className="space-y-2"
                                            >
                                                <p className="text-xs font-bold tracking-[0.05em] uppercase">
                                                    {slot.label}
                                                    {slot.required ? (
                                                        <span
                                                            className="ml-1 text-destructive"
                                                            aria-label="required"
                                                        >
                                                            *
                                                        </span>
                                                    ) : null}
                                                    {slot.multiple ? (
                                                        <span className="ml-1 font-normal text-secondary-foreground">
                                                            · one or more
                                                        </span>
                                                    ) : null}
                                                </p>
                                                <FileUpload
                                                    files={
                                                        files[slot.type] ?? []
                                                    }
                                                    maxFiles={
                                                        slot.multiple
                                                            ? undefined
                                                            : 1
                                                    }
                                                    onFilesChange={(next) =>
                                                        setFiles(
                                                            (currentFiles) => ({
                                                                ...currentFiles,
                                                                [slot.type]:
                                                                    next,
                                                            }),
                                                        )
                                                    }
                                                />
                                                {fileError(slot.type)}
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            ))}
                        </div>
                    )}

                    {extra !== null ? (
                        <div className="space-y-3 border-t border-sidebar-border pt-6">
                            <Label
                                htmlFor="status-amount"
                                className="text-sm font-bold tracking-[0.05em] uppercase"
                            >
                                {EXTRA_LABELS[extra]}
                            </Label>
                            <Input
                                id="status-amount"
                                inputMode="decimal"
                                value={amount}
                                onChange={(event) =>
                                    setAmount(event.target.value)
                                }
                                className="rounded-none"
                                placeholder="0.00"
                            />
                            {error(extra)}
                        </div>
                    ) : null}

                    {error('status')}
                </div>

                <DialogFooter className="mt-6 border-t border-sidebar-border pt-6">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={saving}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={confirm}
                        disabled={!canConfirm || saving}
                    >
                        {saving ? (
                            <Spinner className="motion-reduce:animate-none" />
                        ) : null}
                        Confirm Update
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default StatusUpdateDialog;
