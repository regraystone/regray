import { Check } from 'lucide-react';
import type { InquiryServiceOption } from '@/types/inquiry';

/**
 * One service in the inquiry picker's step 2.
 *
 * This is a full-width row, not a card in a grid. Step 1 is already a grid of
 * stone cards, and repeating that shape one screen later made the two steps
 * read as the same control twice over; a list with rules between the rows and
 * the photo beside the copy is a different shape for a different job, and the
 * long service names get a wide column here instead of wrapping inside a
 * narrow one.
 *
 * The black check badge, the square thumbnail and the round bullet markers all
 * carry over from StoneCard and the stone profile, so the steps still share a
 * visual vocabulary.
 */
const ServiceCard = ({
    service,
    index,
    selected,
    disabled = false,
    onSelect,
}: {
    service: InquiryServiceOption;
    /** 0-based position, shown as 01, 02 and so on. */
    index: number;
    selected: boolean;
    /** It cannot share a project with the services already picked. */
    disabled?: boolean;
    onSelect: () => void;
}) => (
    /*
     * One button fills the row, so a click anywhere on it toggles the service.
     * A native button already turns Space and Enter into a click, so the row
     * does not have to. role="checkbox" is what makes a multi-select group of
     * these read as a set of checkboxes, and aria-checked does double duty as
     * the selected background.
     */
    <button
        type="button"
        role="checkbox"
        aria-checked={selected}
        aria-label={service.name}
        disabled={disabled}
        onClick={onSelect}
        className="flex w-full cursor-pointer items-start gap-4 p-4 text-left transition-colors hover:bg-secondary focus-visible:inset-y-0 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent aria-checked:bg-secondary sm:gap-6 sm:p-5"
    >
        {/*
         * Decoration beside a row that already names the service, hence the
         * empty alt.
         */}
        <span className="relative size-20 shrink-0 overflow-hidden rounded-md bg-background sm:size-28">
            <img
                src={service.image}
                alt=""
                loading="lazy"
                className="absolute inset-0 size-full object-cover"
            />
            {selected ? (
                <span
                    aria-hidden
                    className="absolute top-0 right-0 bg-black p-2 text-white"
                >
                    <Check className="size-3 shrink-0" />
                </span>
            ) : null}
        </span>

        <span className="min-w-0 flex-1">
            <span className="block text-sm font-bold text-foreground sm:text-base">
                {service.name}
            </span>
            {/* Same round marker as the stone profile's bulleted lists. */}
            <ul className="mt-2 space-y-1.5">
                {service.features.map((feature) => (
                    <li
                        key={feature}
                        className="flex items-start gap-2.5 text-sm leading-relaxed text-secondary-foreground"
                    >
                        <span
                            className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"
                            aria-hidden
                        />
                        {feature}
                    </li>
                ))}
            </ul>
        </span>

        {/*
         * The counter balances the thumbnail on wide rows. Below `sm` there is
         * no room for it, and the row is already clear about what it is.
         */}
        <span
            aria-hidden
            className="hidden shrink-0 text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase sm:block"
        >
            {String(index + 1).padStart(2, '0')}
        </span>
    </button>
);

export default ServiceCard;
