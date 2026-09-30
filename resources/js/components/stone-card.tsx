import { Link } from '@inertiajs/react';
import { Check, ExternalLink, Heart, Star } from 'lucide-react';
import type { FeedItem } from '@/data/constant';
import { show as homeShow } from '@/routes/home';

const StoneCard = ({
    item,
    aspect,
    saved,
    onToggleSave,
    href,
    selectable = false,
    selected = false,
    onSelect,
    viewHref,
}: {
    item: FeedItem;
    aspect: string;
    saved: boolean;
    onToggleSave: () => void;
    href?: string;
    /**
     * Turns the card from a link to a stone into a picker.
     *
     * Choosing a stone *is* the action on the inquiry stepper, and its profile
     * is one visit away at most, so the card is better as a toggle than as a
     * link. Without this the card is exactly what it has always been.
     */
    selectable?: boolean;
    selected?: boolean;
    onSelect?: () => void;
    /** An outbound link on the footer, shown only when there is one to show. */
    viewHref?: string;
}) => {
    const heart = (
        <button
            type="button"
            onClick={(event) => {
                // Saved is its own state, and neither the link the card may be
                // nor the picker it may be should be what a heart click does.
                event.preventDefault();
                event.stopPropagation();
                onToggleSave();
            }}
            aria-pressed={saved}
            aria-label={
                saved ? `Remove ${item.name} from saved` : `Save ${item.name}`
            }
            className="grid size-9 shrink-0 cursor-pointer place-items-center transition-opacity hover:opacity-60"
        >
            <Heart
                className={`size-5 ${
                    saved
                        ? 'fill-primary text-primary'
                        : 'text-secondary-foreground'
                }`}
                aria-hidden
            />
        </button>
    );

    const view = viewHref ? (
        <a
            href={viewHref}
            target="_blank"
            rel="noopener noreferrer"
            onClick={(event) => event.stopPropagation()}
            aria-label={`View ${item.name} (opens in a new tab)`}
            className="grid size-9 shrink-0 place-items-center text-secondary-foreground transition-opacity hover:opacity-60"
        >
            <ExternalLink className="size-5" aria-hidden />
        </a>
    ) : null;

    const recommended = item.recommended ? (
        <span className="absolute top-0 left-0 flex items-center gap-1 bg-black p-2 text-[0.65rem] font-bold tracking-[0.05em] text-white">
            <Star
                className="size-3 shrink-0 fill-white text-white"
                aria-hidden
            />
            <span className="hidden sm:inline">Highly Recommended</span>
        </span>
    ) : null;

    if (selectable) {
        // Matches the link card footer: 12px edge padding, 8px gap before the
        // controls, 36px per control (size-9), and a 52px row (py-2 around a
        // 36px control). The name's py-4 around a 20px line is also 52px.
        const namePadding = viewHref ? 'pr-[92px]' : 'pr-14';

        return (
            <article
                className={`relative border bg-background ${
                    selected
                        ? 'border-primary ring-2 ring-primary'
                        : 'border-sidebar-border'
                }`}
            >
                {/*
                 * The toggle covers the image and the name, so a click anywhere
                 * on the card that is not one of the footer controls lands on
                 * it, and their clicks cannot reach it. Keeping them as siblings
                 * of this button rather than children is also what keeps an
                 * interactive element out of another one. A native button
                 * already turns Space and Enter into a click, so the card does
                 * not have to.
                 */}
                <button
                    type="button"
                    role="checkbox"
                    aria-checked={selected}
                    aria-label={item.name}
                    onClick={onSelect}
                    className="block w-full cursor-pointer text-left focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <div
                        className={`stone-card-media relative overflow-hidden bg-background ${aspect}`}
                    >
                        <img
                            src={item.thumbnail}
                            alt=""
                            loading="lazy"
                            className="absolute inset-0 size-full object-cover"
                        />
                        {recommended}
                        {selected ? (
                            <span
                                aria-hidden
                                className="absolute top-0 right-0 bg-black p-2 text-white"
                            >
                                <Check className="size-3 shrink-0" />
                            </span>
                        ) : null}
                    </div>
                    {/* The right padding clears the footer controls. */}
                    <span
                        className={`block min-w-0 truncate py-4 pl-3 text-sm font-bold text-foreground ${namePadding}`}
                    >
                        {item.name}
                    </span>
                </button>

                {/*
                 * Out of flow and over the name row, so the image keeps the
                 * full card width instead of sharing it with the controls.
                 * right-3 and bottom-2 mirror the px-3 and py-2 on the link
                 * card's footer, so the icons sit at the same height there.
                 */}
                <div className="absolute right-3 bottom-2 flex items-center">
                    {heart}
                    {view}
                </div>
            </article>
        );
    }

    return (
        <Link href={href ?? homeShow.url({ stone: item.id })} className="block">
            <article className="stone-card border border-sidebar-border bg-background">
                <div
                    className={`stone-card-media relative overflow-hidden bg-background ${aspect}`}
                >
                    <img
                        src={item.thumbnail}
                        alt={item.name}
                        loading="lazy"
                        className="absolute inset-0 size-full object-cover"
                    />
                    {recommended}
                </div>
                <div className="flex items-center justify-between gap-2 px-3 py-2">
                    <span className="min-w-0 truncate text-sm font-bold text-foreground">
                        {item.name}
                    </span>
                    {heart}
                    {view}
                </div>
            </article>
        </Link>
    );
};

export default StoneCard;
