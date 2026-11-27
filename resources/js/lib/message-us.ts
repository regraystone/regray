/**
 * Where the floating Message us button sits on a platform page, or null where
 * it is not shown. It is a client's way to the team, so staff (who work in
 * Inbox) do not get it, nor does anyone on Inbox itself. On a page that pins
 * its own bar or button to the bottom of the screen it sits higher, so it
 * never covers that: Saved's Ready to source bar, Inquire's Next, Quote's
 * Inquire, and a stone's or piece's bar on a phone or tablet.
 */
const RAISED: Record<string, string> = {
    saved: 'bottom-24',
    inquire: 'bottom-24',
    quote: 'bottom-18',
    'stone-profile':
        'bottom-24 lg:bottom-[max(1rem,env(safe-area-inset-bottom,0px))]',
    'furniture-profile':
        'bottom-24 lg:bottom-[max(1rem,env(safe-area-inset-bottom,0px))]',
};

export const messageUsPlacement = ({
    component,
    roles,
    canMessage,
}: {
    component: string;
    roles: string[];
    canMessage: boolean;
}): string | null => {
    if (!roles.includes('client') || !canMessage || component === 'inbox') {
        return null;
    }

    return (
        RAISED[component] ??
        'bottom-[max(1rem,env(safe-area-inset-bottom,0px))]'
    );
};
