import { ChevronLeft, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Combobox } from '@/components/ui/combobox';
import { distinctLabels } from '@/lib/distinct-labels';

export type MultiSelectOption = { id: number; label: string };

/**
 * Picks several from a list: the chosen ones as chips, each removable, and a
 * search field under them that adds one at a time (the Combobox: type to
 * narrow the list, arrow keys and Enter to choose). What is already chosen is
 * left out of the list. The chips are in the value's order, and each but the
 * first can move one place earlier, so an ordered list is reordered in place.
 * Two options with the same label are told apart (distinctLabels).
 *
 * `extraLabels` names chosen ids that are no longer offered (a stone taken
 * out of the catalogue since), so they still show, to be removed.
 */
const MultiSelect = ({
    id,
    value,
    onChange,
    onBlur,
    options,
    extraLabels = {},
    placeholder,
    listLabel,
    emptyMessage = 'Nothing matches that.',
    describedBy,
    invalid,
}: {
    id: string;
    value: number[];
    onChange: (value: number[]) => void;
    onBlur?: () => void;
    options: MultiSelectOption[];
    extraLabels?: Record<number, string>;
    placeholder?: string;
    listLabel: string;
    emptyMessage?: string;
    describedBy?: string;
    invalid?: boolean;
}) => {
    const [query, setQuery] = useState('');
    const named = useMemo(() => {
        const display = distinctLabels(options, (option) => option.label);

        return options.map((option, index) => ({
            id: option.id,
            label: display[index],
        }));
    }, [options]);
    const labels = useMemo(
        () => new Map(named.map((option) => [option.id, option.label])),
        [named],
    );
    const available = named.filter((option) => !value.includes(option.id));
    const availableLabels = available.map((option) => option.label);

    const choose = (text: string) => {
        const option = available.find((candidate) => candidate.label === text);

        if (option === undefined) {
            setQuery(text);

            return;
        }

        onChange([...value, option.id]);
        setQuery('');
    };

    const remove = (removed: number) => {
        onChange(value.filter((chosen) => chosen !== removed));
        onBlur?.();
    };

    const moveEarlier = (index: number) => {
        const next = [...value];
        [next[index - 1], next[index]] = [next[index], next[index - 1]];
        onChange(next);
    };

    return (
        <div className="space-y-2" onBlur={onBlur}>
            {value.length > 0 ? (
                <ul className="flex flex-wrap gap-2" aria-label={listLabel}>
                    {value.map((chosen, index) => {
                        const label =
                            labels.get(chosen) ??
                            extraLabels[chosen] ??
                            `#${chosen}`;

                        return (
                            <li
                                key={chosen}
                                className="flex items-center gap-1 border border-sidebar-border bg-muted/40 py-1 pr-1 pl-2.5 text-sm"
                            >
                                {index > 0 ? (
                                    <button
                                        type="button"
                                        onClick={() => moveEarlier(index)}
                                        className="-ml-1.5 flex size-6 items-center justify-center text-secondary-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                        aria-label={`Move ${label} earlier`}
                                    >
                                        <ChevronLeft
                                            className="size-3.5"
                                            aria-hidden
                                        />
                                    </button>
                                ) : null}
                                {label}
                                <button
                                    type="button"
                                    onClick={() => remove(chosen)}
                                    className="flex size-6 items-center justify-center text-secondary-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    aria-label={`Remove ${label}`}
                                >
                                    <X className="size-3.5" aria-hidden />
                                </button>
                            </li>
                        );
                    })}
                </ul>
            ) : null}
            <Combobox
                id={id}
                value={query}
                options={availableLabels}
                filter={(text) => {
                    const needle = text.trim().toLowerCase();

                    return needle === ''
                        ? availableLabels
                        : availableLabels.filter((label) =>
                              label.toLowerCase().includes(needle),
                          );
                }}
                listLabel={listLabel}
                placeholder={placeholder}
                emptyMessage={
                    available.length === 0
                        ? 'Every one is chosen.'
                        : emptyMessage
                }
                aria-describedby={describedBy}
                aria-invalid={invalid}
                onChange={choose}
            />
        </div>
    );
};

export default MultiSelect;
