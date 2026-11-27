import { MapPin } from 'lucide-react';
import { createContext, useContext } from 'react';
import { useFormContext, useWatch } from 'react-hook-form';
import AddressPicker from '@/components/address/address-picker';
import { fieldMessage } from '@/components/form-field';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { HELD_ADDRESS, type AddressSource } from '@/lib/inquiry-flow';
import type {
    InquiryDraftInput,
    InquiryDraftValues,
} from '@/lib/inquiry-schema';
import type { AddressValue, PhAddressPayload } from '@/lib/ph-address';
import { cn } from '@/lib/utils';
import type { SavedAddress } from '@/types/inquiry';

/** The id the page scrolls to when the address is missing. */
export const INQUIRY_ADDRESS_ID = 'inquiry-address';

/**
 * Where the Inquire page's addresses come from: the client's own book, which
 * they manage on Profile → Addresses, or (staff) the address picker.
 */
export type InquiryAddressContextValue = {
    source: AddressSource;
    /** The client's saved addresses, the default first. Empty for staff. */
    addresses: SavedAddress[];
    /** The address an inquiry being edited already has, which may have left the book. */
    heldAddress: PhAddressPayload | null;
    /** Save the draft and go to the address book, to come back to it. */
    onManageAddresses: () => void;
};

export const InquiryAddressContext = createContext<InquiryAddressContextValue>({
    source: 'picker',
    addresses: [],
    heldAddress: null,
    onManageAddresses: () => undefined,
});

export const useInquiryAddresses = () => useContext(InquiryAddressContext);

/**
 * The line a project's address reads as on Review: the saved address it
 * names, the one it already has, or the picker's value. Null when none.
 */
export const projectAddressLine = (
    context: InquiryAddressContextValue,
    project: { addressId: string; address: AddressValue | null },
    picked: (address: AddressValue) => string,
): string | null => {
    if (context.source === 'picker') {
        return project.address === null ? null : picked(project.address);
    }

    if (project.addressId === HELD_ADDRESS) {
        return context.heldAddress?.line ?? null;
    }

    return (
        context.addresses.find(({ id }) => String(id) === project.addressId)
            ?.address.line ?? null
    );
};

/**
 * The project's delivery or installation address.
 *
 * A client picks one of their saved addresses, the default already picked;
 * they add or change them on Profile → Addresses ("Manage addresses"), never
 * here. With none saved there is nothing to pick, and the request cannot be
 * sent until one is added. Staff enter the address with the picker.
 */
const InquiryAddressField = ({
    label,
    projectNumber,
}: {
    /** "Delivery address" or "Installation address". */
    label: string;
    projectNumber: number;
}) => {
    const context = useInquiryAddresses();
    const {
        control,
        setValue,
        formState: { errors, isSubmitted },
    } = useFormContext<InquiryDraftInput, unknown, InquiryDraftValues>();
    const addressId = useWatch({ control, name: 'addressId' });
    const address = useWatch({ control, name: 'address' });
    const headingId = `${INQUIRY_ADDRESS_ID}-${projectNumber}-label`;
    const error =
        fieldMessage(errors.addressId) ?? fieldMessage(errors.address);

    if (context.source === 'picker') {
        return (
            <fieldset
                id={INQUIRY_ADDRESS_ID}
                className="min-w-0 md:col-span-2"
                aria-describedby={error ? `${headingId}-error` : undefined}
            >
                <legend id={headingId} className="mb-3 text-sm font-bold">
                    {label}
                    <span className="text-destructive" aria-hidden>
                        {' *'}
                    </span>
                    <span className="sr-only"> (required)</span>
                </legend>
                <InputError
                    id={`${headingId}-error`}
                    message={error}
                    role="alert"
                    className="mb-3"
                />
                <AddressPicker
                    idPrefix={`inquiry-address-${projectNumber}`}
                    value={(address ?? null) as AddressValue | null}
                    onChange={(next) =>
                        setValue('address', next, {
                            shouldDirty: true,
                            shouldValidate: isSubmitted,
                        })
                    }
                />
            </fieldset>
        );
    }

    const choices: Array<{
        value: string;
        label: string;
        line: string;
        isDefault: boolean;
        held?: boolean;
    }> = [
        ...(context.heldAddress !== null
            ? [
                  {
                      value: HELD_ADDRESS,
                      label: 'The address this request has',
                      line: context.heldAddress.line,
                      isDefault: false,
                      held: true,
                  },
              ]
            : []),
        ...context.addresses.map((saved) => ({
            value: String(saved.id),
            label: saved.label,
            line: saved.address.line,
            isDefault: saved.isDefault,
        })),
    ];

    return (
        <div id={INQUIRY_ADDRESS_ID} className="min-w-0 md:col-span-2">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p id={headingId} className="text-sm font-bold">
                    {label}
                    <span className="text-destructive" aria-hidden>
                        {' *'}
                    </span>
                    <span className="sr-only"> (required)</span>
                </p>
                {choices.length > 0 ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={context.onManageAddresses}
                        data-test="inquiry-manage-addresses"
                    >
                        Manage addresses
                    </Button>
                ) : null}
            </div>

            {choices.length === 0 ? (
                <div
                    className="flex flex-col items-start gap-3 border border-dashed border-sidebar-border p-4 sm:flex-row sm:items-center sm:justify-between"
                    data-test="inquiry-no-addresses"
                >
                    <div className="flex items-start gap-3">
                        <MapPin
                            aria-hidden
                            className="mt-0.5 size-5 shrink-0"
                        />
                        <div>
                            <p className="font-bold">
                                Add an address to continue
                            </p>
                            <p className="text-sm text-secondary-foreground">
                                Save it in your address book. Your answers here
                                are kept for when you come back.
                            </p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        onClick={context.onManageAddresses}
                        className="w-full shrink-0 sm:w-auto"
                    >
                        Add an address
                    </Button>
                </div>
            ) : (
                <RadioGroup
                    aria-labelledby={headingId}
                    aria-describedby={error ? `${headingId}-error` : undefined}
                    value={addressId}
                    onValueChange={(next) =>
                        setValue('addressId', next, {
                            shouldDirty: true,
                            shouldValidate: isSubmitted,
                        })
                    }
                    className="grid gap-2 sm:grid-cols-2"
                >
                    {choices.map((choice) => {
                        const id = `${INQUIRY_ADDRESS_ID}-${projectNumber}-${choice.value}`;

                        return (
                            <Label
                                key={choice.value}
                                htmlFor={id}
                                className={cn(
                                    'flex min-w-0 cursor-pointer items-start gap-3 border p-3 font-normal',
                                    addressId === choice.value
                                        ? 'border-foreground'
                                        : 'border-sidebar-border',
                                )}
                            >
                                <RadioGroupItem
                                    id={id}
                                    value={choice.value}
                                    className="mt-0.5"
                                />
                                <span className="min-w-0">
                                    <span className="flex flex-wrap items-center gap-2 font-bold break-words">
                                        {choice.label}
                                        {choice.isDefault ? (
                                            <Badge variant="secondary">
                                                Default
                                            </Badge>
                                        ) : null}
                                    </span>
                                    <span className="mt-0.5 block text-sm break-words text-secondary-foreground">
                                        {choice.line}
                                    </span>
                                </span>
                            </Label>
                        );
                    })}
                </RadioGroup>
            )}

            <InputError
                id={`${headingId}-error`}
                message={error}
                role="alert"
                className="mt-2"
            />
        </div>
    );
};

export default InquiryAddressField;
