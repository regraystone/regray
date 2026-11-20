import { X } from 'lucide-react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { addTags } from '@/lib/brand-admin';

/**
 * A short list of words typed one at a time, such as what a brand makes: type
 * one, then Enter or a comma, to add it; × takes one off, and so does
 * Backspace in the empty box. Empty and repeated tags are left out, and none
 * past `max` (addTags()); the box goes once the list is full.
 */
const TagInput = ({
    id,
    value,
    onChange,
    onBlur,
    max,
    maxLength,
    placeholder,
    describedBy,
    invalid,
}: {
    id: string;
    value: string[];
    onChange: (tags: string[]) => void;
    onBlur?: () => void;
    max: number;
    maxLength: number;
    placeholder?: string;
    describedBy?: string;
    invalid?: boolean;
}) => {
    const [draft, setDraft] = useState('');
    const full = value.length >= max;

    const commit = (text: string) => {
        const next = addTags(value, text, max);

        if (next.length !== value.length) {
            onChange(next);
        }

        setDraft('');
    };

    return (
        <div className="mt-2 space-y-2">
            {value.length > 0 ? (
                <ul className="flex flex-wrap gap-2" aria-label="Added">
                    {value.map((tag) => (
                        <li
                            key={tag}
                            className="inline-flex items-center gap-1 border border-sidebar-border bg-muted/40 py-1 pr-1 pl-2.5 text-sm"
                        >
                            {tag}
                            <button
                                type="button"
                                onClick={() =>
                                    onChange(
                                        value.filter((item) => item !== tag),
                                    )
                                }
                                className="inline-flex size-6 items-center justify-center text-secondary-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transition-none"
                                aria-label={`Remove ${tag}`}
                            >
                                <X className="size-3.5" aria-hidden />
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}

            {full ? null : (
                <Input
                    id={id}
                    value={draft}
                    maxLength={maxLength}
                    placeholder={placeholder}
                    autoComplete="off"
                    className="rounded-none"
                    aria-describedby={describedBy}
                    aria-invalid={invalid}
                    onChange={(event) => {
                        const text = event.target.value;

                        // A comma typed or pasted adds what is before it.
                        if (text.includes(',')) {
                            commit(text);
                        } else {
                            setDraft(text);
                        }
                    }}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            // Adds a tag instead of sending the form.
                            event.preventDefault();
                            commit(draft);
                        } else if (
                            event.key === 'Backspace' &&
                            draft === '' &&
                            value.length > 0
                        ) {
                            onChange(value.slice(0, -1));
                        }
                    }}
                    onBlur={() => {
                        commit(draft);
                        onBlur?.();
                    }}
                />
            )}
        </div>
    );
};

export default TagInput;
