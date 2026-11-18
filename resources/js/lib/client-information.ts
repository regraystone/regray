import { z } from 'zod';

/**
 * The client's own "Client Information" on a new request: only how they would
 * like to get it and, for a delivery, where it goes.
 *
 * Deliberately free of `@/` aliases: the pure parts of this module are imported
 * by the Node tests as well as the page. The delivery values mirror the PHP
 * `DeliveryMethod` enum, and the site list is the one the inquiry already uses.
 */
export const DELIVERY = 'delivery';
export const PICKUP = 'pickup';

export const CLIENT_INFORMATION_FIELDS = [
    'deliveryMethod',
    'location',
] as const;

export type ClientInformationValues = {
    deliveryMethod: string;
    location: string | null;
};

/** The form's own shape: an unanswered site is `''`, never null. */
export type ClientInformationFormValues = {
    deliveryMethod: string;
    location: string;
};

export type ClientInformationField = (typeof CLIENT_INFORMATION_FIELDS)[number];

export type ClientInformationErrors = Partial<
    Record<ClientInformationField, string>
>;

/** What the server is handed with a client-submitted inquiry. */
export type ClientInformationPayload = {
    deliveryMethod: string;
    location: string | null;
    saveToProfile: boolean;
};

/** The props a new request receives: saved values and what to start from. */
export type ClientInformationPageProps = {
    /** Null until the client has saved a row. */
    saved: ClientInformationValues | null;
    /** Always present for a client, even before anything has been saved. */
    prefill: ClientInformationValues;
};

export const clientInformationFormValues = (
    values: ClientInformationValues,
): ClientInformationFormValues => ({
    deliveryMethod: values.deliveryMethod,
    location: values.location ?? '',
});

/** The saved-row shape: a pickup and a blank answer both hold no site. */
export const clientInformationForStorage = (
    values: ClientInformationFormValues,
): ClientInformationValues => ({
    deliveryMethod: values.deliveryMethod.trim(),
    location:
        values.deliveryMethod === PICKUP || values.location.trim() === ''
            ? null
            : values.location.trim(),
});

/**
 * Compare the project's answer with what is saved.
 *
 * A null saved site and an unanswered form site are one answer. A pickup never
 * carries a site, so the location is left out of that comparison. A saved row
 * is required for the choice to be hidden; a client with none always gets it.
 */
export const clientInformationDiffers = (
    saved: ClientInformationValues,
    current: ClientInformationFormValues,
): boolean => {
    if (saved.deliveryMethod.trim() !== current.deliveryMethod.trim()) {
        return true;
    }

    if (current.deliveryMethod === PICKUP) {
        return false;
    }

    return (saved.location ?? '').trim() !== current.location.trim();
};

/**
 * The starting answer for a new project.
 *
 * A restored draft wins: it is the reader's own half-finished answer, and must
 * not be overwritten by a Profile value that has not changed since. `''` is an
 * answer the draft actually holds; a site no longer offered is not, and falls
 * through to the saved site like a stale Profile value would.
 */
export const projectAnswersForNewProject = (
    prefill: ClientInformationValues,
    locations: readonly string[],
    restoredDeliveryMethod?: string | null,
    restoredLocation?: string | null,
): ClientInformationFormValues => {
    const validMethod = (value: string | null | undefined): string | null =>
        value === DELIVERY || value === PICKUP ? value : null;
    const validLocation = (value: string | null | undefined): string | null =>
        typeof value === 'string' && locations.includes(value) ? value : null;

    const restored = validMethod(restoredDeliveryMethod);
    const deliveryMethod =
        restored ?? validMethod(prefill.deliveryMethod) ?? DELIVERY;
    let location = '';

    if (deliveryMethod === DELIVERY) {
        if (restored !== null && restoredLocation === '') {
            // An unanswered site is an answer a saved draft actually holds.
            location = '';
        } else {
            location =
                validLocation(restoredLocation) ??
                validLocation(prefill.location) ??
                '';
        }
    }

    return { deliveryMethod, location };
};

/** The same two answers the server's form request applies. */
export const clientInformationSchema = (locations: readonly string[]) =>
    z
        .object({
            deliveryMethod: z.enum([DELIVERY, PICKUP], {
                error: 'Choose how you would like to get it.',
            }),
            location: z.string().trim(),
        })
        .superRefine((values, context) => {
            if (values.deliveryMethod === PICKUP) {
                return;
            }

            if (values.location === '') {
                context.addIssue({
                    code: 'custom',
                    path: ['location'],
                    message: 'Choose where the site is.',
                });

                return;
            }

            if (!locations.includes(values.location)) {
                context.addIssue({
                    code: 'custom',
                    path: ['location'],
                    message: 'Choose a city or municipality from the list.',
                });
            }
        });

/** Turn a zod failure into one message per field, in the form's own order. */
export const clientInformationZodErrors = (
    issues: Array<{ path: readonly PropertyKey[]; message: string }>,
): ClientInformationErrors => {
    const errors: ClientInformationErrors = {};

    for (const issue of issues) {
        const field = issue.path[0];

        if (
            typeof field === 'string' &&
            CLIENT_INFORMATION_FIELDS.includes(
                field as ClientInformationField,
            ) &&
            errors[field as ClientInformationField] === undefined
        ) {
            errors[field as ClientInformationField] = issue.message;
        }
    }

    return errors;
};

/** The complaint paths the server sends, read back into the Review block. */
export const mapClientInformationErrors = (
    errors: Record<string, string>,
): ClientInformationErrors => {
    const messages: ClientInformationErrors = {};

    for (const field of CLIENT_INFORMATION_FIELDS) {
        const message = errors[`clientInformation.${field}`];

        if (typeof message === 'string') {
            messages[field] = message;
        }
    }

    return messages;
};

/**
 * Add the two answers and the save choice to a multipart request.
 *
 * Nothing here is ever written to a draft: browser storage is not a place for
 * details the client asked us to keep on their Profile.
 */
export const appendClientInformationFormData = (
    body: FormData,
    values: ClientInformationFormValues,
    saveToProfile: boolean,
): void => {
    const field = (name: string): string => `clientInformation[${name}]`;

    body.append(field('deliveryMethod'), values.deliveryMethod.trim());
    body.append(
        field('location'),
        values.deliveryMethod === PICKUP ? '' : values.location.trim(),
    );
    body.append(field('saveToProfile'), saveToProfile ? '1' : '0');
};
