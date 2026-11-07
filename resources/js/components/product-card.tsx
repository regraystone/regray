import { Link } from '@inertiajs/react';
import { Heart, ImageOff } from 'lucide-react';
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
    category: { slug: string; name: string };
};

const ProductCard = ({
    product,
    aspect: _aspect,
    saved,
    onToggleSave,
    href,
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

    const heart = renderHeart(true);

    const placeholder = (
        <div
            className="absolute inset-0 grid place-items-center bg-muted text-secondary-foreground/50"
            aria-hidden
        >
            <ImageOff className="size-8" />
        </div>
    );

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
                {heart ? (
                    <div className="absolute top-3 right-3 z-10 hidden items-center gap-1 rounded-md bg-black/60 p-1 text-white opacity-0 backdrop-blur-sm transition-opacity duration-200 group-focus-within:opacity-100 group-hover:opacity-100 lg:flex">
                        {heart}
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
                {onToggleSave ? (
                    <div className="-mt-2 flex shrink-0 items-center lg:hidden">
                        {renderHeart(false)}
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
