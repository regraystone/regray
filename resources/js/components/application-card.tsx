import { Check } from 'lucide-react';

/**
 * One application tile in the inquiry picker's Application step (step 3).
 *
 * The options are the project categories, so each tile is a portrait photo
 * with the name overlaid at the bottom-left on a dark gradient scrim. Selected
 * state shows a ring border and a check badge in the top-right, matching the
 * stone card's vocabulary.
 *
 * A project may hold several applications, so each tile toggles like a checkbox.
 */
const ApplicationCard = ({
    application,
    selected,
    onSelect,
}: {
    application: { id: string; name: string; image: string };
    selected: boolean;
    onSelect: () => void;
}) => (
    <button
        type="button"
        role="checkbox"
        aria-checked={selected}
        aria-label={application.name}
        onClick={onSelect}
        className={`relative aspect-[4/5] w-full overflow-hidden rounded-md border transition-all focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none ${
            selected
                ? 'border-primary ring-2 ring-primary'
                : 'border-sidebar-border'
        }`}
    >
        <img
            src={application.image}
            alt=""
            loading="lazy"
            className="absolute inset-0 size-full object-cover"
        />

        <div className="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-black/80 to-transparent" />

        <span className="absolute bottom-0 left-0 p-3 text-start text-xs font-bold tracking-[0.05em] text-white uppercase">
            {application.name}
        </span>

        {/* Check badge in top-right when selected */}
        {selected ? (
            <span
                aria-hidden
                className="absolute top-0 right-0 rounded-md bg-black p-2 text-white"
            >
                <Check className="size-3 shrink-0" />
            </span>
        ) : null}
    </button>
);

export default ApplicationCard;
