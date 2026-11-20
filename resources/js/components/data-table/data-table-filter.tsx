import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type DataTableFilterOption = {
    value: string;
    label: string;
};

/**
 * One of a list's filters, as a select. A page may draw as many as it filters
 * by; each says what it filters in its accessible name.
 */
const DataTableFilter = ({
    label,
    options,
    value,
    onChange,
}: {
    /** What it filters, such as "Status": the select's accessible name. */
    label: string;
    options: DataTableFilterOption[];
    value: string;
    onChange: (value: string) => void;
}) => (
    <Select value={value} onValueChange={onChange}>
        <SelectTrigger
            className="w-full rounded-none sm:w-40"
            aria-label={label}
        >
            <SelectValue />
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

export default DataTableFilter;
