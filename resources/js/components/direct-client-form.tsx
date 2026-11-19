import { useState, type FormEvent, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ApiError } from '@/lib/message-api';
import {
    createDirectClient,
    type DirectClientInput,
    type DirectClientResult,
} from '@/lib/staff-clients-api';
import { CONTACT_KIND_LABELS } from '@/lib/staff-inquire';

/**
 * Take down a direct (walk-in) client: their full name and phone number, and
 * their email when they give one. Nothing else: where the work goes is asked
 * on each inquiry, with the address picker. When the phone or the email is
 * already another client's, nothing is created and that client is offered
 * instead (`use`), so the same client is not added twice.
 *
 * Shared by the staff Inquire flow (Direct) and Book an appointment (Walk-in).
 * `header` draws the heading above it, told whether the duplicate is being
 * offered. Its submit never reaches a form it is drawn inside, as when it
 * opens in a dialog over the booking form.
 */
const DirectClientForm = ({
    onUse,
    header,
    useFor,
}: {
    onUse: (result: DirectClientResult) => void;
    header?: (offeringExisting: boolean) => ReactNode;
    /** What the duplicate is to be used for: "Open the inquiry for them". */
    useFor: string;
}) => {
    const [form, setForm] = useState<DirectClientInput>({
        fullName: '',
        phone: '',
        email: '',
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [saving, setSaving] = useState(false);
    const [existing, setExisting] = useState<DirectClientResult | null>(null);

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        event.stopPropagation();
        setSaving(true);
        setErrors({});

        try {
            const result = await createDirectClient(form);

            if (result.existing) {
                setExisting(result);
            } else {
                onUse(result);
            }
        } catch (error) {
            setErrors(
                error instanceof ApiError
                    ? error.errors
                    : { fullName: ['The client could not be saved.'] },
            );
        } finally {
            setSaving(false);
        }
    };

    const error = (field: keyof DirectClientInput) =>
        errors[field]?.[0] ? (
            <p
                id={`direct-${field}-error`}
                className="text-xs text-destructive"
            >
                {errors[field][0]}
            </p>
        ) : null;

    const describedBy = (field: keyof DirectClientInput) =>
        errors[field]?.[0] ? `direct-${field}-error` : undefined;

    if (existing !== null) {
        return (
            <div>
                {header?.(true)}
                <div
                    className="space-y-4 rounded-md border border-sidebar-border p-4"
                    data-testid="direct-client-duplicate"
                >
                    <p className="text-sm">
                        <span className="font-bold">
                            {existing.client.fullName}
                        </span>{' '}
                        already has this{' '}
                        {existing.matched
                            ? `${CONTACT_KIND_LABELS[existing.matched.kind].toLowerCase()} (${existing.matched.value})`
                            : 'contact'}
                        . {useFor} instead of adding the same client twice.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" onClick={() => onUse(existing)}>
                            Use {existing.client.fullName}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setExisting(null)}
                        >
                            Change the details
                        </Button>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <form
            onSubmit={(event) => void submit(event)}
            noValidate
            data-testid="direct-client-form"
        >
            {header?.(false)}

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5 sm:col-span-2">
                    <Label htmlFor="direct-full-name">
                        Full name
                        <span className="text-destructive" aria-hidden>
                            {' *'}
                        </span>
                        <span className="sr-only"> (required)</span>
                    </Label>
                    <Input
                        id="direct-full-name"
                        value={form.fullName}
                        onChange={(event) =>
                            setForm({ ...form, fullName: event.target.value })
                        }
                        autoComplete="name"
                        maxLength={150}
                        aria-invalid={describedBy('fullName') !== undefined}
                        aria-describedby={describedBy('fullName')}
                        className="rounded-none"
                    />
                    {error('fullName')}
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="direct-phone">
                        Phone number
                        <span className="text-destructive" aria-hidden>
                            {' *'}
                        </span>
                        <span className="sr-only"> (required)</span>
                    </Label>
                    <Input
                        id="direct-phone"
                        type="tel"
                        inputMode="tel"
                        value={form.phone}
                        onChange={(event) =>
                            setForm({ ...form, phone: event.target.value })
                        }
                        autoComplete="tel"
                        maxLength={30}
                        aria-invalid={describedBy('phone') !== undefined}
                        aria-describedby={describedBy('phone')}
                        className="rounded-none"
                    />
                    {error('phone')}
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="direct-email">Email (optional)</Label>
                    <Input
                        id="direct-email"
                        type="email"
                        value={form.email}
                        onChange={(event) =>
                            setForm({ ...form, email: event.target.value })
                        }
                        autoComplete="email"
                        maxLength={255}
                        aria-invalid={describedBy('email') !== undefined}
                        aria-describedby={describedBy('email')}
                        className="rounded-none"
                    />
                    {error('email')}
                </div>
            </div>

            <div className="mt-8 flex justify-end">
                <Button type="submit" disabled={saving}>
                    {saving ? (
                        <Spinner className="motion-reduce:animate-none" />
                    ) : null}
                    Continue
                </Button>
            </div>
        </form>
    );
};

export default DirectClientForm;
