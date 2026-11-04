import { Link } from '@inertiajs/react';
import { Check, ExternalLink, Heart, ImageOff, Star } from 'lucide-react';
import { useState } from 'react';

/**
 * What a product card shows: a stone or slab, or a piece of furniture. The
 * `category` is the line under the name (a stone's natural stone, a piece's
 * project type). A product with no photo yet shows a neutral placeholder.
 */
export type ProductCardData = {
    id?: string;
    slug: string;
    name: string;
    thumbnail: string | null;
    bestSeller?: boolean;
    category: { slug: string; name: string };
};

const ProductCard = ({
    product,
    aspect: _aspect,
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
    product: ProductCardData;
    aspect?: string;
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

    /*
     * The same heart is drawn twice: over the picture and in the name row.
     * Only one of them is ever laid out — the picture copy from `lg` up, where
     * it fades in on hover, and the name-row copy below that, where a touch
     * screen has no hover to reveal anything with. `display: none` also takes
     * the hidden copy out of the tab order and the accessibility tree, so the
     * heart is never announced twice.
     */
    const renderHeart = (onPicture: boolean) =>
        onToggleSave ? (
            <button
                type="button"
                onClick={(event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    onToggleSave();
                }}
                aria-pressed={saved}
                aria-label={
                    saved
                        ? `Remove ${product.name} from saved`
                        : `Save ${product.name}`
                }
                className="grid h-9 w-9 shrink-0 cursor-pointer place-items-center rounded-md transition-opacity hover:opacity-60 active:scale-90"
            >
                <Heart
                    key={heartAnimation}
                    className={`size-5 ${
                        saved
                            ? 'fill-red-500 text-red-500'
                            : onPicture
                              ? 'text-white'
                              : 'text-secondary-foreground'
                    } ${heartAnimation}`}
                    aria-hidden
                />
            </button>
        ) : null;

    const renderView = (onPicture: boolean) =>
        viewHref ? (
            <a
                href={viewHref}
                target="_blank"
                rel="noopener noreferrer"
                onClick={(event) => event.stopPropagation()}
                aria-label={`View ${product.name} (opens in a new tab)`}
                className={`grid h-9 w-9 shrink-0 place-items-center rounded-md transition-opacity hover:opacity-60 ${
                    onPicture ? 'text-white' : 'text-secondary-foreground'
                }`}
            >
                <ExternalLink className="size-5" aria-hidden />
            </a>
        ) : null;

    const heart = renderHeart(true);
    const view = renderView(true);

    const placeholder = (
        <div
            className="absolute inset-0 grid place-items-center bg-muted text-secondary-foreground/50"
            aria-hidden
        >
            <ImageOff className="size-8" />
        </div>
    );

    const bestSeller = product.bestSeller ? (
        <span className="absolute top-0 left-0 flex items-center gap-1 rounded-none bg-black p-2 text-[0.65rem] font-bold tracking-[0.05em] text-white uppercase">
            <Star
                className="size-3 shrink-0 fill-white text-white"
                aria-hidden
            />
            Best Seller
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
                    aria-label={product.name}
                    onClick={onSelect}
                    className="block w-full cursor-pointer rounded-none bg-background text-left focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <div className="stone-card-media relative aspect-square w-full overflow-hidden bg-background">
                        {product.thumbnail ? (
                            <img
                                src={product.thumbnail}
                                alt=""
                                loading={priority ? 'eager' : 'lazy'}
                                className="absolute inset-0 size-full object-cover"
                            />
                        ) : (
                            placeholder
                        )}
                        {bestSeller}
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
                         * best-seller tag is top-left, the tick top-right, and
                         * this is the one tag that can appear on a card that is
                         * also a best seller.
                         */}
                        {note ? (
                            <span className="absolute bottom-0 left-0 rounded-none bg-black px-2 py-1.5 text-[0.65rem] font-bold tracking-[0.05em] text-white uppercase">
                                {note}
                            </span>
                        ) : null}
                    </div>
                    {/* The right padding clears the footer controls. */}
                    <span
                        className={`block min-w-0 py-3 pl-3 text-sm font-bold text-foreground ${namePadding}`}
                    >
                        <span
                            className="line-clamp-2 break-words uppercase"
                            title={product.name}
                        >
                            {product.name}
                        </span>
                        <span className="block text-xs font-normal break-words text-secondary-foreground">
                            {product.category.name}
                        </span>
                    </span>
                </button>

                {/*
                 * Out of flow and over the name row, so the image keeps the
                 * full card width instead of sharing it with the controls.
                 */}
                <div className="absolute right-3 bottom-2 flex items-center">
                    {renderHeart(false)}
                    {renderView(false)}
                </div>
            </article>
        );
    }

    /*
     * The card is a link when there is somewhere to send it and plain content
     * when there is not: a stone profile sits under Home or under Saved, and a
     * page that may open neither hands over a card that simply shows the
     * product. Same answer the project card already gives.
     *
     * Only the best-seller tag sits on the picture, top left; the name and the
     * category are text under it. The name wraps onto a second line and is
     * cut with an ellipsis after that; the title shows it whole on hover.
     */
    const content = (
        <article className="stone-card group overflow-visible">
            <div className="stone-card-media relative aspect-square w-full overflow-hidden rounded-none bg-muted">
                {product.thumbnail ? (
                    <img
                        src={product.thumbnail}
                        alt={product.name}
                        loading={priority ? 'eager' : 'lazy'}
                        className="absolute inset-0 size-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                ) : (
                    placeholder
                )}
                {bestSeller}
                {heart || view ? (
                    <div className="absolute top-3 right-3 z-10 hidden items-center gap-1 rounded-md bg-black/60 p-1 text-white opacity-0 backdrop-blur-sm transition-opacity duration-200 group-focus-within:opacity-100 group-hover:opacity-100 lg:flex">
                        {heart}
                        {view}
                    </div>
                ) : null}
            </div>
            <div className="flex items-start justify-between gap-2 pt-3">
                <span className="min-w-0 flex-1">
                    <span
                        className="line-clamp-2 text-base leading-snug font-medium break-words text-foreground uppercase"
                        title={product.name}
                    >
                        {product.name}
                    </span>
                    <span className="mt-0.5 block text-sm break-words text-secondary-foreground">
                        {product.category.name}
                    </span>
                </span>
                {/*
                 * Below `lg` there is no hover to reveal the picture's heart,
                 * so it sits at the top right of the name row, level with the
                 * first line. From `lg` up the picture copy takes over and this
                 * one is not laid out.
                 */}
                {onToggleSave || viewHref ? (
                    <div className="-mt-2 flex shrink-0 items-center lg:hidden">
                        {renderHeart(false)}
                        {renderView(false)}
                    </div>
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

export default ProductCard;
