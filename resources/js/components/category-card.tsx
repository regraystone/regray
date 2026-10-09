import { Check } from 'lucide-react';
import type { ProjectCategory } from '@/data/projects';

/**
 * One category tile in the inquiry picker's Category step (step 3).
 *
 * A portrait tile with full-bleed photo and category name overlaid at the
 * bottom-left on a dark gradient scrim for contrast. Selected state shows a
 * ring border and check badge in the top-right, matching stone card vocabulary.
 *
 * A project may hold several categories, so each tile toggles like a checkbox.
 */
const CategoryCard = ({
    category,
    selected,
    onSelect,
}: {
    category: { value: ProjectCategory; label: string; image: string };
    selected: boolean;
    onSelect: () => void;
}) => (
    <button
        type="button"
        role="checkbox"
        aria-checked={selected}
        aria-label={category.label}
        onClick={onSelect}
        className={`relative aspect-[4/5] w-full overflow-hidden border transition-all focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none ${
            selected
                ? 'border-primary ring-2 ring-primary'
                : 'border-sidebar-border'
        }`}
    >
        <img
            src={category.image}
            alt=""
            loading="lazy"
            className="absolute inset-0 size-full object-cover"
        />

        {/* Dark gradient scrim at the bottom for text contrast */}
        <div className="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-black/80 to-transparent" />

        {/* Category label: bottom-left, white, bold uppercase */}
        <span className="absolute bottom-0 left-0 p-3 text-start text-xs font-bold tracking-[0.05em] text-white uppercase">
            {category.label}
        </span>

        {/* Check badge in top-right when selected */}
        {selected ? (
            <span
                aria-hidden
                className="absolute top-0 right-0 bg-black p-2 text-white"
            >
                <Check className="size-3 shrink-0" />
            </span>
        ) : null}
    </button>
);

export default CategoryCard;
