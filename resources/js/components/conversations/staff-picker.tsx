import type { Person } from '@/lib/message-api';

/** "1 active inquiry", "2 active inquiries". */
export const activeInquiryLabel = (count: number): string =>
    `${count} active ${count === 1 ? 'inquiry' : 'inquiries'}`;

/**
 * One staff role's place in a group chat: its people as radio buttons, each
 * with how many open inquiries they are assigned to, so the picker can spread
 * the work. A group takes exactly one person per role, which the server
 * enforces; the count is only there to inform the choice.
 */
const StaffPicker = ({
    name,
    legend,
    people,
    selected,
    onSelect,
    empty,
    error = null,
}: {
    name: string;
    legend: string;
    people: Person[];
    selected: number | null;
    onSelect: (id: number) => void;
    empty: string;
    error?: string | null;
}) => (
    <fieldset className="space-y-1">
        <legend className="text-sm font-bold">{legend}</legend>
        {people.length === 0 ? (
            <p className="text-xs text-secondary-foreground">{empty}</p>
        ) : (
            <ul className="max-h-40 overflow-y-auto">
                {people.map((person) => (
                    <li key={person.id}>
                        <label className="flex cursor-pointer items-center gap-2 py-1.5 text-sm">
                            <input
                                type="radio"
                                name={name}
                                checked={selected === person.id}
                                onChange={() => onSelect(person.id)}
                            />
                            <span className="min-w-0 flex-1 truncate">
                                {person.name}
                            </span>
                            {person.activeInquiryCount !== undefined ? (
                                <span
                                    className="shrink-0 text-xs text-secondary-foreground"
                                    data-testid="active-inquiry-count"
                                >
                                    {activeInquiryLabel(
                                        person.activeInquiryCount,
                                    )}
                                </span>
                            ) : null}
                        </label>
                    </li>
                ))}
            </ul>
        )}
        {error !== null ? (
            <p className="text-xs text-destructive">{error}</p>
        ) : null}
    </fieldset>
);

export default StaffPicker;
