import { Check } from 'lucide-react';

/**
 * A stone to pick on the Inquire page: its picture, its name and its stone type.
 * The whole card is the checkbox, so there is nothing else on it to press. It
 * fills the height it is given, so a row of cards is as tall as its tallest,
 * with the picture and name kept at the top (a button centres them otherwise).
 *
 * A circle in the picture's corner is the tick: see-through until the stone
 * is picked, then black with a check. Picked, its frame also thickens inward
 * (an outline inset just inside the border, drawn over the picture) rather
 * than outward, so a card at the end of the scrolling row is not cut off and
 * the row needs no room beyond its own width.
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
                ? 'border-foreground outline-2 -outline-offset-2 outline-foreground'
                : 'border-border hover:border-foreground'
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
                <span
                    aria-hidden
                    className={`absolute top-2.5 right-2.5 flex size-6.5 items-center justify-center rounded-full border border-white shadow-[0_1px_4px_rgb(0_0_0/0.25)] ${
                        selected ? 'bg-foreground text-white' : 'bg-white/35'
                    }`}
                >
                    {selected ? <Check className="size-3.5 shrink-0" /> : null}
                </span>
            </span>
            <span className="block min-w-0 px-3.5 pt-3 pb-3.5">
                <span
                    className="line-clamp-2 text-sm font-semibold tracking-[0.02em] break-words text-foreground uppercase"
                    title={name}
                >
                    {name}
                </span>
                <span className="mt-0.5 block text-[0.8125rem] break-words text-muted-foreground">
                    {typeName}
                </span>
            </span>
        </button>
    </article>
);

export default StoneCard;
