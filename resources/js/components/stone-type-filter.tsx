import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type StoneTypeFilterProps = {
    options: Array<{ value: string; label: string }>;
    value: string;
    onChange: (value: string) => void;
};

/** From `md` up: a dropdown, after the Saved, All and Best Seller tabs. */
export const StoneTypeSelect = ({
    options,
    value,
    onChange,
}: StoneTypeFilterProps) => (
    <div className="hidden w-52 shrink-0 md:block">
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger
                aria-label="Filter by stone type"
                className="w-full rounded-none bg-background"
                data-testid="stone-type-select"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem
                        key={option.value}
                        value={option.value}
                        className="rounded-none"
                    >
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    </div>
);

/**
 * Below `md`: a divider under the tabs, then a row of pills that scrolls sideways.
 * The row takes focus, so a keyboard can scroll it with the arrow keys.
 */
export const StoneTypePills = ({
    options,
    value,
    onChange,
}: StoneTypeFilterProps) => (
    <div className="md:hidden">
        <div aria-hidden className="my-3 h-px bg-sidebar-border" />
        <div
            role="group"
            aria-label="Filter by stone type"
            tabIndex={0}
            className="flex [scrollbar-width:none] gap-2 overflow-x-auto whitespace-nowrap focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none [&::-webkit-scrollbar]:hidden"
            data-testid="stone-type-pills"
        >
            {options.map((option) => {
                const isSelected = option.value === value;

                return (
                    <Button
                        key={option.value}
                        type="button"
                        size="sm"
                        variant={isSelected ? 'default' : 'outline'}
                        aria-pressed={isSelected}
                        onClick={() => onChange(option.value)}
                        className="shrink-0 px-3 py-1.5"
                    >
                        {option.label}
                    </Button>
                );
            })}
        </div>
    </div>
);
