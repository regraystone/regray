import { Check, Store, Truck, Wrench } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import {
    SCHEDULE_KINDS,
    SCHEDULE_KIND_LABELS,
    type ScheduleKind,
} from '@/lib/schedule-kinds';

const ICONS: Record<ScheduleKind, LucideIcon> = {
    showroom_visit: Store,
    installation: Wrench,
    delivery: Truck,
};

const DESCRIPTIONS: Record<ScheduleKind, string> = {
    showroom_visit: 'See our stones and furniture at the showroom.',
    installation: 'Choose a day for our team to install your order.',
    delivery: 'Choose a day for us to deliver your order.',
};

/** What a choice says when none of the client's orders can take it yet. */
const NOTHING_YET: Record<ScheduleKind, string> = {
    showroom_visit: '',
    installation: 'No order to install yet',
    delivery: 'No order to deliver yet',
};

/**
 * What the client would like to schedule: a showroom visit, an installation
 * or a delivery, as three cards. An installation or a delivery none of their
 * accepted orders can take yet is shown but cannot be picked, and says why.
 */
const ScheduleKindPicker = ({
    value,
    onChange,
    available,
}: {
    value: ScheduleKind;
    onChange: (kind: ScheduleKind) => void;
    /** Whether each kind has something to book. */
    available: Record<ScheduleKind, boolean>;
}) => (
    <div>
        <p
            id="schedule-kind-label"
            className="text-sm font-medium text-foreground"
        >
            What would you like to schedule?
        </p>
        <div
            role="radiogroup"
            aria-labelledby="schedule-kind-label"
            className="mt-2 grid gap-2 sm:grid-cols-3"
        >
            {SCHEDULE_KINDS.map((kind) => {
                const Icon = ICONS[kind];
                const selected = value === kind;
                const disabled = !available[kind];

                return (
                    <button
                        key={kind}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        disabled={disabled}
                        data-testid={`schedule-kind-${kind}`}
                        onClick={() => onChange(kind)}
                        className={cn(
                            'relative flex flex-col items-start gap-2 border p-4 text-left transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                            selected
                                ? 'border-foreground bg-muted'
                                : 'border-border hover:bg-muted/60',
                            disabled && 'cursor-not-allowed opacity-50',
                        )}
                    >
                        <Icon className="size-5" aria-hidden />
                        <span className="text-sm font-semibold">
                            {SCHEDULE_KIND_LABELS[kind]}
                        </span>
                        <span className="text-xs leading-normal text-muted-foreground">
                            {disabled ? NOTHING_YET[kind] : DESCRIPTIONS[kind]}
                        </span>
                        {selected ? (
                            <Check
                                className="absolute top-3 right-3 size-4"
                                aria-hidden
                            />
                        ) : null}
                    </button>
                );
            })}
        </div>
    </div>
);

export default ScheduleKindPicker;
