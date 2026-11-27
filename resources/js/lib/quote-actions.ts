/*
 * The buttons on a Quote card. The server decides which exist and which can be
 * pressed (see `App\Policies\InquiryActions`); this leaves out any that cannot be
 * pressed and puts the rest in the order the card draws them in.
 *
 * No imports, on purpose: the tests under tests/js run this file straight under
 * Node, where the `@/` alias does not exist.
 */

export type QuoteActionKey = 'view' | 'update' | 'cancel' | 'invoice';

/** One action as the list resource sends it. */
export type QuoteActionState = {
    visible: boolean;
    enabled: boolean;
    /** Present only on an action that is shown and cannot be pressed. */
    reason?: string;
};

export type QuoteActions = Record<QuoteActionKey, QuoteActionState>;

export type CardButton = {
    key: QuoteActionKey;
    label: string;
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
 * The card's main button, named for where the inquiry stands (its Quotations
 * tab, `stage`): its details before a quotation, the quotation once one is
 * waiting, the order once it is accepted. Each opens the same details page.
 */
export const mainButtonLabel = (
    stage: 'pending' | 'quoted' | 'accepted' | null,
): string =>
    stage === 'quoted'
        ? 'View quotation'
        : stage === 'accepted'
          ? 'View order'
          : 'View details';

/** The buttons to draw, in order: only the actions that can be pressed. */
export const buildCardActions = (actions: QuoteActions): CardButton[] =>
    CARD_BUTTON_ORDER.flatMap((key) => {
        const state = actions[key];

        if (!state.visible || !state.enabled) {
            return [];
        }

        return [{ key, ...BUTTONS[key] }];
    });
