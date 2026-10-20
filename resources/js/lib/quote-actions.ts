/*
 * The buttons on a Quote card. The server decides which exist and which can be
 * pressed (see `App\Policies\InquiryActions`); this only puts what it sent into
 * the order the card draws them in.
 *
 * No imports, on purpose: the tests under tests/js run this file straight under
 * Node, where the `@/` alias does not exist.
 */

export type QuoteActionKey = 'view' | 'update' | 'cancel' | 'invoice';

/** One action as the list resource sends it. */
export type QuoteActionState = {
    visible: boolean;
    enabled: boolean;
    /** Present only on a button that is shown and cannot be pressed. */
    reason?: string;
};

export type QuoteActions = Record<QuoteActionKey, QuoteActionState>;

export type CardButton = {
    key: QuoteActionKey;
    label: string;
    enabled: boolean;
    reason?: string;
    variant: 'default' | 'outline';
};

/** The order the buttons are drawn in, top to bottom. */
export const CARD_BUTTON_ORDER: readonly QuoteActionKey[] = [
    'view',
    'update',
    'cancel',
    'invoice',
];

const BUTTONS: Record<
    QuoteActionKey,
    { label: string; variant: CardButton['variant'] }
> = {
    view: { label: 'View details', variant: 'default' },
    update: { label: 'Update', variant: 'outline' },
    cancel: { label: 'Cancel', variant: 'outline' },
    invoice: { label: 'Get invoice', variant: 'outline' },
};

/**
 * The buttons to draw, in order. An action that is not visible is not in the
 * list at all; one that is visible but not enabled carries the server's reason.
 */
export const buildCardActions = (actions: QuoteActions): CardButton[] =>
    CARD_BUTTON_ORDER.flatMap((key) => {
        const state = actions[key];

        if (!state.visible) {
            return [];
        }

        const { label, variant } = BUTTONS[key];

        return [
            {
                key,
                label,
                variant,
                enabled: state.enabled,
                ...(!state.enabled && state.reason !== undefined
                    ? { reason: state.reason }
                    : {}),
            },
        ];
    });
