import InputError from '@/components/input-error';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/** A labelled dropdown of the Inquire page's lists: its service types and services. */
const ServiceOptionSelect = ({
    id,
    label = 'What do you need?',
    labelContext,
    value,
    onChange,
    error,
    options,
    describedBy,
}: {
    id: string;
    label?: string;
    /** Which of several rows this is, for a screen reader; the row shows it. */
    labelContext?: string;
    /** The entry picked, '' when none yet. */
    value: string;
    onChange: (option: string) => void;
    error?: string;
    options: Array<{ id: string; name: string }>;
    /** The id of what explains the entry picked, for a screen reader. */
    describedBy?: string;
}) => (
    <>
        <label
            htmlFor={id}
            className="block text-xs font-bold tracking-[0.16em] text-secondary-foreground uppercase"
        >
            {label}
            {labelContext ? (
                <span className="sr-only"> for {labelContext}</span>
            ) : null}
        </label>
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger
                id={id}
                aria-invalid={error ? true : undefined}
                aria-describedby={
                    [describedBy, error ? `${id}-error` : undefined]
                        .filter(Boolean)
                        .join(' ') || undefined
                }
                className="mt-2 w-full rounded-none bg-background sm:max-w-md"
                data-testid="service-option"
            >
                <SelectValue placeholder="Choose one" />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem
                        key={option.id}
                        value={option.id}
                        className="rounded-none"
                    >
                        {option.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
        <InputError
            id={`${id}-error`}
            message={error}
            role="alert"
            className="mt-2"
        />
    </>
);

export default ServiceOptionSelect;
