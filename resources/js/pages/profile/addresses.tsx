import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, MapPin, Pencil, Plus, Star, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import ClientAddressController from '@/actions/App/Http/Controllers/Settings/ClientAddressController';
import AddressPicker, {
    type AddressPickerErrors,
} from '@/components/address/address-picker';
import ConfirmDialog from '@/components/confirm-dialog';
import FormField from '@/components/form-field';
import PageHeading from '@/components/page-heading';
import { Badge } from '@/components/ui/badge';
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
import {
    addressFromPayload,
    addressToRequest,
    pickerErrors,
    savedAddressProblems,
    type AddressValue,
} from '@/lib/ph-address';
import { cn } from '@/lib/utils';
import type { SavedAddress } from '@/types/inquiry';

type PageProps = {
    addresses: SavedAddress[];
    limit: number;
    quickLabels: string[];
    /** The Inquire page, when the client came here from it. */
    returnTo: string | null;
};

type Editing = {
    id: number | null;
    label: string;
    address: AddressValue | null;
};

/** The way back to the Inquire page is kept on every save. */
const fromInquire = (returnTo: string | null) =>
    returnTo === null ? {} : { query: { from: 'inquire' } };

/**
 * The client's address book: up to `limit` places, one of them the default
 * the Inquire page starts from. Adding and editing happen in a dialog (the
 * whole screen on a phone) with the address picker; deleting asks first.
 * The first address added is the default; "Set as default" moves it, and
 * deleting the default makes the most recently changed one the default.
 */
export default function Addresses({
    addresses,
    limit,
    quickLabels,
    returnTo,
}: PageProps) {
    const [editing, setEditing] = useState<Editing | null>(null);
    const [labelError, setLabelError] = useState<string | undefined>();
    const [addressErrors, setAddressErrors] = useState<AddressPickerErrors>({});
    const [formError, setFormError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);
    const [deleting, setDeleting] = useState<SavedAddress | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);

    const full = addresses.length >= limit;

    const open = (next: Editing) => {
        setLabelError(undefined);
        setAddressErrors({});
        setFormError(undefined);
        setEditing(next);
    };

    const save = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editing === null) {
            return;
        }

        const problems = savedAddressProblems(editing);

        setLabelError(problems.label);
        setAddressErrors(problems.address);

        if (
            problems.label !== undefined ||
            Object.keys(problems.address).length > 0
        ) {
            return;
        }

        const data = {
            label: editing.label.trim(),
            address: addressToRequest(editing.address as AddressValue),
        };
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setEditing(null),
            onError: (errors: Record<string, string>) => {
                setLabelError(errors.label);
                setAddressErrors(pickerErrors(errors));
                setFormError(errors.address);
            },
        };

        if (editing.id === null) {
            router.post(
                ClientAddressController.store.url(fromInquire(returnTo)),
                data,
                options,
            );
        } else {
            router.put(
                ClientAddressController.update.url(
                    editing.id,
                    fromInquire(returnTo),
                ),
                data,
                options,
            );
        }
    };

    const makeDefault = (saved: SavedAddress) =>
        router.post(
            ClientAddressController.makeDefault.url(
                saved.id,
                fromInquire(returnTo),
            ),
            {},
            {
                preserveScroll: true,
                onStart: () => setBusyId(saved.id),
                onFinish: () => setBusyId(null),
            },
        );

    const remove = () => {
        if (deleting === null) {
            return;
        }

        router.delete(
            ClientAddressController.destroy.url(
                deleting.id,
                fromInquire(returnTo),
            ),
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setDeleting(null),
            },
        );
    };

    return (
        <>
            <Head title="Addresses" />

            <h1 className="sr-only">Addresses</h1>

            <div className="mx-auto w-full max-w-3xl">
                {/*
                 * One way back. On a phone or tablet it is the page's Back, which
                 * returns to the request when opened from Inquire (`returnTo`) and
                 * to Profile otherwise; a laptop has the breadcrumbs instead, so
                 * only the way back to the request is drawn there.
                 */}
                {returnTo !== null ? (
                    <Link
                        href={returnTo}
                        className="mb-5 hidden items-center gap-2 text-sm text-secondary-foreground transition-opacity hover:opacity-70 lg:inline-flex"
                        data-test="addresses-back-to-inquiry"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                        Back to your request
                    </Link>
                ) : null}

                <div className="space-y-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <PageHeading
                            title="Addresses"
                            description="Where we deliver to or install at. Your default is picked first on a new request."
                        />

                        <div className="flex flex-col items-start gap-1 sm:items-end">
                            <Button
                                type="button"
                                disabled={full}
                                aria-describedby={
                                    full ? 'addresses-limit' : undefined
                                }
                                onClick={() =>
                                    open({ id: null, label: '', address: null })
                                }
                                data-test="addresses-add"
                            >
                                <Plus aria-hidden className="size-4" />
                                Add address
                            </Button>
                            <p
                                id="addresses-limit"
                                className="text-xs text-secondary-foreground"
                            >
                                {full
                                    ? `You have ${limit} addresses, the most you can keep. Delete one to add another.`
                                    : `${addresses.length} of ${limit} addresses`}
                            </p>
                        </div>
                    </div>

                    {addresses.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 border border-dashed border-sidebar-border px-6 py-12 text-center">
                            <MapPin aria-hidden className="size-6 opacity-60" />
                            <p className="font-bold">No addresses yet</p>
                            <p className="max-w-sm text-sm text-secondary-foreground">
                                Add the places we may deliver to or install at.
                                The first one you add becomes your default.
                            </p>
                        </div>
                    ) : (
                        <ul className="grid gap-3" data-test="addresses-list">
                            {addresses.map((saved) => (
                                <li
                                    key={saved.id}
                                    className={cn(
                                        'flex flex-col gap-3 border p-4 sm:flex-row sm:items-start sm:justify-between',
                                        saved.isDefault
                                            ? 'border-foreground'
                                            : 'border-sidebar-border',
                                    )}
                                    data-test="address-card"
                                >
                                    <div className="min-w-0">
                                        <p className="flex flex-wrap items-center gap-2 font-bold break-words">
                                            {saved.label}
                                            {saved.isDefault ? (
                                                <Badge variant="secondary">
                                                    Default
                                                </Badge>
                                            ) : null}
                                        </p>
                                        <p className="mt-1 text-sm break-words text-secondary-foreground">
                                            {saved.address.line}
                                        </p>
                                    </div>

                                    <div className="flex shrink-0 flex-wrap gap-2">
                                        {saved.isDefault ? null : (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={busyId === saved.id}
                                                onClick={() =>
                                                    makeDefault(saved)
                                                }
                                            >
                                                <Star
                                                    aria-hidden
                                                    className="size-4"
                                                />
                                                Set as default
                                            </Button>
                                        )}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            aria-label={`Edit ${saved.label}`}
                                            onClick={() =>
                                                open({
                                                    id: saved.id,
                                                    label: saved.label,
                                                    address: addressFromPayload(
                                                        saved.address,
                                                    ),
                                                })
                                            }
                                        >
                                            <Pencil
                                                aria-hidden
                                                className="size-4"
                                            />
                                            Edit
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            aria-label={`Delete ${saved.label}`}
                                            onClick={() => setDeleting(saved)}
                                        >
                                            <Trash2
                                                aria-hidden
                                                className="size-4"
                                            />
                                            Delete
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(next) => {
                    if (!next && !processing) {
                        setEditing(null);
                    }
                }}
            >
                <DialogContent className="inset-0 top-0 h-dvh max-h-dvh rounded-none border-0 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:h-auto sm:max-h-[calc(100dvh-2rem)] sm:w-[calc(100vw-2rem)] sm:max-w-3xl sm:rounded-md sm:border lg:max-w-5xl">
                    {editing !== null ? (
                        <form
                            onSubmit={save}
                            className="grid min-w-0 content-start gap-5"
                            noValidate
                        >
                            <DialogHeader>
                                <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                                    {editing.id === null
                                        ? 'Add address'
                                        : 'Edit address'}
                                </DialogTitle>
                                <DialogDescription>
                                    Pick each place from the list, or put the
                                    pin on the map.
                                </DialogDescription>
                            </DialogHeader>

                            <FormField
                                id="address-label"
                                label="Label"
                                required
                                error={labelError}
                                hint="Your own name for it, such as Home or the site's name."
                            >
                                {(accessibility) => (
                                    <div className="grid gap-2">
                                        <Input
                                            id="address-label"
                                            value={editing.label}
                                            maxLength={50}
                                            onChange={(event) =>
                                                setEditing({
                                                    ...editing,
                                                    label: event.target.value,
                                                })
                                            }
                                            aria-describedby={
                                                accessibility.describedBy
                                            }
                                            aria-invalid={accessibility.invalid}
                                            className="rounded-none"
                                        />
                                        <div
                                            className="flex flex-wrap gap-2"
                                            role="group"
                                            aria-label="Quick labels"
                                        >
                                            {quickLabels.map((quick) => (
                                                <Button
                                                    key={quick}
                                                    type="button"
                                                    size="sm"
                                                    variant={
                                                        editing.label === quick
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                    aria-pressed={
                                                        editing.label === quick
                                                    }
                                                    onClick={() =>
                                                        setEditing({
                                                            ...editing,
                                                            label: quick,
                                                        })
                                                    }
                                                >
                                                    {quick}
                                                </Button>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </FormField>

                            <AddressPicker
                                idPrefix="address-book"
                                value={editing.address}
                                onChange={(address) =>
                                    setEditing((current) =>
                                        current === null
                                            ? current
                                            : { ...current, address },
                                    )
                                }
                                errors={addressErrors}
                            />

                            {formError !== undefined ? (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {formError}
                                </p>
                            ) : null}

                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={processing}
                                    onClick={() => setEditing(null)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="address-save"
                                >
                                    {processing ? 'Saving…' : 'Save address'}
                                </Button>
                            </DialogFooter>
                        </form>
                    ) : null}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        setDeleting(null);
                    }
                }}
                title={`Delete ${deleting?.label ?? 'this address'}?`}
                description={
                    deleting?.isDefault && addresses.length > 1
                        ? 'This is your default. The address you changed most recently becomes the default instead. Requests already sent keep their own copy.'
                        : 'Requests already sent keep their own copy of it.'
                }
                confirmLabel="Delete address"
                cancelLabel="Keep address"
                destructive
                processing={processing}
                onConfirm={remove}
            />
        </>
    );
}
