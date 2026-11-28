/**
 * Where the floating Message us button sits on a platform page, or null where
 * it is not shown. It is a client's way to the team, so staff (who work in
 * Inbox) do not get it. Nor do the pages in HIDDEN. On a page that pins its
 * own bar or button to the bottom of the screen it sits higher, so it never
 * covers that: Saved's Ready to source bar.
 */
const RAISED: Record<string, string> = {
    saved: 'bottom-24',
};

/**
 * Pages with no Message us, as Sir Mark asked: Inbox, where the chats already
 * are, a stone's and a piece's page, whose own bar holds Ask a quote, the
 * Quotations page, and Inquire (sending or editing a request), where it
 * distracted from the form.
 */
const HIDDEN = [
    'inbox',
    'stone-profile',
    'furniture-profile',
    'quote',
    'inquire',
];

export const messageUsPlacement = ({
    component,
    roles,
    canMessage,
}: {
    component: string;
    roles: string[];
    canMessage: boolean;
}): string | null => {
    if (
        !roles.includes('client') ||
        !canMessage ||
        HIDDEN.includes(component)
    ) {
        return null;
    }

    return (
        RAISED[component] ??
        'bottom-[max(1rem,env(safe-area-inset-bottom,0px))]'
    );
};
