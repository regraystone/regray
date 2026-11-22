import { useEffect, useState } from 'react';
import { Combobox } from '@/components/ui/combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type ChoiceOption = { value: string; label: string };

/** Lists longer than this are searched rather than scrolled. */
export const SEARCH_FROM = 8;

/**
 * Picks one from a list in a form, the same height and text size as the
 * form's text fields (Input). A short list is a dropdown; a longer one (more
 * than `searchFrom`) is a search field over the list (the Combobox: type to
 * narrow it, arrow keys and Enter to choose), so a long list of brands or
 * sizes isn't scrolled through.
 *
 * The value is the chosen option's `value`, or '' for none. Text typed in the
 * search that names no option leaves the value empty, so the form says to
 * choose one.
 */
const ChoiceSelect = ({
    id,
    value,
    onChange,
    onBlur,
    options,
    placeholder,
    listLabel,
    emptyMessage = 'Nothing matches that.',
    describedBy,
    invalid,
    searchFrom = SEARCH_FROM,
    className,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    options: ChoiceOption[];
    placeholder: string;
    /** What the list is, for a screen reader; the placeholder when left out. */
    listLabel?: string;
    emptyMessage?: string;
    describedBy?: string;
    invalid?: boolean;
    searchFrom?: number;
    className?: string;
}) => {
    const chosen = options.find((option) => option.value === value);
    const [query, setQuery] = useState(chosen?.label ?? '');

    // A value set from outside (the form reset, an edit opened) shows its label.
    useEffect(() => {
        setQuery((current) => {
            const named = options.find((option) => option.label === current);

            return named?.value === value ? current : (chosen?.label ?? '');
        });
    }, [value, chosen, options]);

    if (options.length <= searchFrom) {
        return (
            <Select value={value || undefined} onValueChange={onChange}>
                <SelectTrigger
                    id={id}
                    className={cn(
                        'mt-2 w-full rounded-none text-base data-[size=default]:h-11',
                        className,
                    )}
                    aria-describedby={describedBy}
                    aria-invalid={invalid}
                    onBlur={onBlur}
                >
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        );
    }

    const labels = options.map((option) => option.label);

    return (
        <div className={cn('mt-2', className)} onBlur={onBlur}>
            <Combobox
                id={id}
                value={query}
                options={labels}
                filter={(text) => {
                    const needle = text.trim().toLowerCase();

                    return needle === ''
                        ? labels
                        : labels.filter((label) =>
                              label.toLowerCase().includes(needle),
                          );
                }}
                listLabel={listLabel ?? placeholder}
                placeholder={placeholder}
                emptyMessage={emptyMessage}
                aria-describedby={describedBy}
                aria-invalid={invalid}
                onChange={(text) => {
                    setQuery(text);
                    const option = options.find(
                        (candidate) => candidate.label === text,
                    );
                    const next = option?.value ?? '';

                    if (next !== value) {
                        onChange(next);
                    }
                }}
            />
        </div>
    );
};

export default ChoiceSelect;
