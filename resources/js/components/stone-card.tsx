import { Link } from '@inertiajs/react';
import { Check, ExternalLink, Heart, Star } from 'lucide-react';
import { useState } from 'react';
import type { StoneCardData } from '@/types/stone';

type Stone = StoneCardData;

const StoneCard = ({
    stone,
    aspect,
    saved,
    onToggleSave,
    href,
    selectable = false,
    selected = false,
    onSelect,
    viewHref,
    note,
    priority = false,
}: {
    stone: Stone;
    aspect: string;
    saved: boolean;
    /**
     * Optional callback when the user clicks the save heart.
     * When omitted, no heart is rendered.
     */
    onToggleSave?: () => void;
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
    /**
     * A short tag over the picture, said once and left out when there is none.
     *
     * On the inquiry stepper this is how a stone that is already part of another
     * project says so. It is a tag rather than a dimmed or disabled card because
     * the same stone in two projects is a real request — a honed floor and a
     * polished top of one material — and a card that refused the second pick
     * would say it was not.
     */
    note?: string;
    priority?: boolean;
}) => {
    /*
     * The heart only animates when `saved` flips from a tap. Comparing the
     * previous value during render means the class lands in the same commit,
     * so the heart never paints a frame in the wrong fill. On mount the two
     * match, so a heart that was already saved stays still.
     */
    const [previousSaved, setPreviousSaved] = useState(saved);
    const [heartAnimation, setHeartAnimation] = useState('');

    if (previousSaved !== saved) {
        setPreviousSaved(saved);
        setHeartAnimation(saved ? 'heart-save' : 'heart-unsave');
    }

    const heart = onToggleSave ? (
        <button
            type="button"
            onClick={(event) => {
                event.preventDefault();
                event.stopPropagation();
                onToggleSave();
            }}
            aria-pressed={saved}
            aria-label={
                saved ? `Remove ${stone.name} from saved` : `Save ${stone.name}`
            }
            className="grid h-11 w-11 shrink-0 cursor-pointer place-items-center rounded-md transition-opacity hover:opacity-60 active:scale-90"
        >
            <Heart
                key={heartAnimation}
                className={`size-5 ${
                    saved
                        ? 'fill-red-500 text-red-500'
                        : 'text-secondary-foreground'
                } ${heartAnimation}`}
                aria-hidden
            />
        </button>
    ) : null;

    const view = viewHref ? (
        <a
            href={viewHref}
            target="_blank"
            rel="noopener noreferrer"
            onClick={(event) => event.stopPropagation()}
            aria-label={`View ${stone.name} (opens in a new tab)`}
            className="grid h-11 w-11 shrink-0 place-items-center rounded-md text-secondary-foreground transition-opacity hover:opacity-60"
        >
            <ExternalLink className="size-5" aria-hidden />
        </a>
    ) : null;

    const recommended = stone.recommended ? (
        <span className="absolute top-0 left-0 flex items-center gap-1 rounded-none bg-black p-2 text-[0.65rem] font-bold tracking-[0.05em] text-white">
            <Star
                className="size-3 shrink-0 fill-white text-white"
                aria-hidden
            />
            <span className="hidden xl:inline">Highly Recommended</span>
        </span>
    ) : null;

    if (selectable) {
        // The footer is at least 60px tall: 44px controls with 8px above and
        // below, or the name and category's 36px with 12px above and below,
        // and it grows by a line when a long name wraps to two. Reserve the controls' width
        // plus their 12px right inset.
        const namePadding = viewHref ? 'pr-[100px]' : 'pr-14';

        return (
            <article
                className={`relative overflow-hidden rounded-none border ${
                    selected
                        ? 'border-primary bg-primary ring-2 ring-primary'
                        : 'border-sidebar-border bg-background'
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
                    aria-label={stone.name}
                    onClick={onSelect}
                    className="block w-full cursor-pointer rounded-none bg-background text-left focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <div
                        className={`stone-card-media relative overflow-hidden bg-background ${aspect}`}
                    >
                        <img
                            src={stone.thumbnail}
                            alt=""
                            loading={priority ? 'eager' : 'lazy'}
                            className="absolute inset-0 size-full object-cover"
                        />
                        {recommended}
                        {selected ? (
                            <span
                                aria-hidden
                                className="absolute top-0 right-0 rounded-none bg-primary p-2 text-white"
                            >
                                <Check className="size-3 shrink-0" />
                            </span>
                        ) : null}
                        {/*
                         * Bottom-left, where nothing else sits: the
                         * recommendation is top-left, the tick top-right, and
                         * this is the one tag that can appear on a card that is
                         * also recommended.
                         */}
                        {note ? (
                            <span className="absolute bottom-0 left-0 rounded-none bg-black px-2 py-1.5 text-[0.65rem] font-bold tracking-[0.05em] text-white">
                                {note}
                            </span>
                        ) : null}
                    </div>
                    {/* The right padding clears the footer controls. */}
                    <span
                        className={`block min-w-0 py-3 pl-3 text-sm font-bold text-foreground ${namePadding}`}
                    >
                        <span
                            className="line-clamp-2 break-words"
                            title={stone.name}
                        >
                            {stone.name}
                        </span>
                        <span className="block text-xs font-normal break-words text-secondary-foreground">
                            {stone.category.name}
                        </span>
                    </span>
                </button>

                {/*
                 * Out of flow and over the name row, so the image keeps the
                 * full card width instead of sharing it with the controls.
                 */}
                <div className="absolute right-3 bottom-2 flex items-center">
                    {heart}
                    {view}
                </div>
            </article>
        );
    }

    /*
     * The card is a link when there is somewhere to send it and plain content
     * when there is not: a stone profile sits under Home or under Saved, and a
     * page that may open neither hands over a card that simply shows the
     * stone. Same answer the project card already gives.
     *
     * Nothing sits on the picture: the name, the category and the
     * recommendation are text under it. The name wraps onto a second line and
     * is cut with an ellipsis after that; the title shows it whole on hover.
     */
    const content = (
        <article className="stone-card overflow-visible">
            <div
                className={`stone-card-media relative overflow-hidden rounded-none bg-muted ${aspect}`}
            >
                <img
                    src={stone.thumbnail}
                    alt={stone.name}
                    loading={priority ? 'eager' : 'lazy'}
                    className="absolute inset-0 size-full object-cover"
                />
            </div>
            <div className="flex items-start justify-between gap-2 pt-3">
                <span className="min-w-0 flex-1">
                    <span
                        className="line-clamp-2 text-lg leading-snug font-medium break-words text-foreground"
                        title={stone.name}
                    >
                        {stone.name}
                    </span>
                    <span className="mt-0.5 block text-sm break-words text-secondary-foreground">
                        {stone.category.name}
                    </span>
                    {stone.recommended ? (
                        <span className="mt-1 flex items-center gap-1 text-xs text-secondary-foreground">
                            <Star
                                className="size-3 shrink-0 fill-current"
                                aria-hidden
                            />
                            Highly Recommended
                        </span>
                    ) : null}
                </span>
                {heart || view ? (
                    // Pulled up and out so the 20px icons line up with the
                    // name's first line and the image's right edge, while the
                    // 44px tap targets stay whole.
                    <span className="-mt-2 -mr-3 flex shrink-0 items-center">
                        {heart}
                        {view}
                    </span>
                ) : null}
            </div>
        </article>
    );

    if (href) {
        return (
            <Link href={href} className="block">
                {content}
            </Link>
        );
    }

    return content;
};

export default StoneCard;
