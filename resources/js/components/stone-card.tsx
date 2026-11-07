import { Check } from 'lucide-react';

/**
 * A stone to pick on the Inquire page: its picture, its name and its stone type.
 * The whole card is the checkbox, so there is nothing else on it to press. It
 * fills the height it is given, so a row of cards is as tall as its tallest,
 * with the picture and name kept at the top (a button centres them otherwise).
 */
const StoneCard = ({
    name,
    typeName,
    thumbnail,
    selected,
    onToggle,
}: {
    name: string;
    typeName: string;
    thumbnail: string;
    selected: boolean;
    onToggle: () => void;
}) => (
    <article
        className={`h-full overflow-hidden rounded-none border bg-background ${
            selected
                ? 'border-primary ring-2 ring-primary'
                : 'border-sidebar-border'
        }`}
        data-testid="stone-card"
    >
        <button
            type="button"
            role="checkbox"
            aria-checked={selected}
            aria-label={name}
            onClick={onToggle}
            className="flex h-full w-full cursor-pointer flex-col justify-start rounded-none bg-background text-left focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            <span className="relative block aspect-square w-full overflow-hidden bg-muted">
                <img
                    src={thumbnail}
                    alt=""
                    loading="lazy"
                    className="absolute inset-0 size-full object-cover"
                />
                {selected ? (
                    <span
                        aria-hidden
                        className="absolute top-0 right-0 rounded-none bg-primary p-2 text-white"
                    >
                        <Check className="size-3 shrink-0" />
                    </span>
                ) : null}
            </span>
            <span className="block min-w-0 p-3">
                <span
                    className="line-clamp-2 text-sm font-bold break-words text-foreground uppercase"
                    title={name}
                >
                    {name}
                </span>
                <span className="block text-xs break-words text-secondary-foreground">
                    {typeName}
                </span>
            </span>
        </button>
    </article>
);

export default StoneCard;
