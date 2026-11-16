import { TriangleAlert } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { slotLabel } from '@/lib/booking-slots';
import {
    clashWarning,
    searchAssignableSales,
    type AssignableSales,
    type SalesClash,
    type SalesPickerTime,
} from '@/lib/schedule-sales-api';

/** The overlap warning under a sales member's name. */
const ClashNote = ({ clash }: { clash: SalesClash }) => (
    <span
        className="mt-0.5 flex items-center gap-1 text-xs font-medium text-amber-700 dark:text-amber-400"
        data-testid="sales-clash"
    >
        <TriangleAlert aria-hidden className="size-3.5 shrink-0" />
        <span className="truncate">{clashWarning(clash, slotLabel)}</span>
    </span>
);

/**
 * Who confirms the appointment: one sales member, found by name, each shown
 * with how many appointments are still to happen with them. Chosen, it reads
 * as their name with a Change button, like the client picker beside it.
 *
 * Given `at` (the time being booked, or the appointment being assigned), a
 * sales member who already has an appointment then is marked with a warning,
 * and still chosen if marketing wants them. The warning follows `at`, so it is
 * checked again when the day or time changes after they were chosen.
 *
 * Used by Book an appointment and by an appointment's Assign dialog.
 */
const SalesPicker = ({
    value,
    onChange,
    error,
    at = null,
    exceptId = null,
}: {
    value: AssignableSales | null;
    onChange: (sales: AssignableSales | null) => void;
    error?: string;
    at?: SalesPickerTime | null;
    /** Left out of the list: the sales member it is already assigned to. */
    exceptId?: number | null;
}) => {
    const [search, setSearch] = useState('');
    const [people, setPeople] = useState<AssignableSales[]>([]);
    const [hasSearched, setHasSearched] = useState(false);
    const [chosenClash, setChosenClash] = useState<SalesClash | null>(null);
    const atKey = at === null ? '' : JSON.stringify(at);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            // Chosen: only whether they clash at `at` is still asked.
            searchAssignableSales(value === null ? search : value.name, at)
                .then((response) => {
                    if (value === null) {
                        setPeople(response.sales);
                    } else {
                        setChosenClash(
                            response.sales.find(
                                (person) => person.id === value.id,
                            )?.clash ?? null,
                        );
                    }
                })
                .catch(() => {
                    if (value === null) {
                        setPeople([]);
                    }
                })
                .finally(() => setHasSearched(true));
        }, 200);

        return () => window.clearTimeout(timer);
        // `atKey` stands for `at`, which callers rebuild on every render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, value?.id, atKey]);

    const errorId = 'sales-picker-error';
    const listed = people.filter((person) => person.id !== exceptId);
    const workload = (count: number): string =>
        count === 0
            ? 'No upcoming appointments'
            : `${count} upcoming appointment${count === 1 ? '' : 's'}`;

    return (
        <div data-testid="sales-picker">
            <p className="text-sm font-medium text-foreground">
                Sales member to confirm it <span aria-hidden>*</span>
            </p>

            {value !== null ? (
                <div className="mt-2 flex items-center justify-between gap-3 rounded-md border border-sidebar-border px-3 py-2">
                    <span className="min-w-0">
                        <span className="block truncate text-sm font-bold">
                            {value.name}
                        </span>
                        <span className="block truncate text-xs text-secondary-foreground">
                            {workload(value.openAppointmentCount)}
                        </span>
                        {chosenClash ? <ClashNote clash={chosenClash} /> : null}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            setChosenClash(null);
                            onChange(null);
                        }}
                        aria-label={`Change sales member: ${value.name}`}
                    >
                        Change
                    </Button>
                </div>
            ) : (
                <>
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search sales by name"
                        aria-label="Search sales members"
                        aria-invalid={error !== undefined}
                        aria-describedby={error ? errorId : undefined}
                        className="mt-2 rounded-none"
                    />
                    {listed.length === 0 ? (
                        hasSearched ? (
                            <p className="mt-2 text-sm text-secondary-foreground">
                                No sales member matches.
                            </p>
                        ) : null
                    ) : (
                        <ul className="mt-2 max-h-56 divide-y divide-sidebar-border overflow-y-auto rounded-md border border-sidebar-border">
                            {listed.map((person) => (
                                <li key={person.id}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setChosenClash(person.clash);
                                            onChange(person);
                                        }}
                                        className="block w-full px-3 py-2 text-left transition-opacity hover:opacity-80 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    >
                                        <span className="block truncate text-sm font-bold">
                                            {person.name}
                                        </span>
                                        <span className="block truncate text-xs text-secondary-foreground">
                                            {workload(
                                                person.openAppointmentCount,
                                            )}
                                        </span>
                                        {person.clash ? (
                                            <ClashNote clash={person.clash} />
                                        ) : null}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </>
            )}

            {error ? (
                <p id={errorId} className="mt-1 text-sm text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
};

export default SalesPicker;
