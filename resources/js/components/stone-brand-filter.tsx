import { ChevronDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { InquiryBrandOption } from '@/types/inquiry';

/**
 * The stones' Brand filter: the brands the project's Brand answer chose (every
 * active brand with No preference), any number of them, starting at all of
 * them (none picked). It ANDs with the tabs, the type and the search, and is
 * asked of the server (`stone_brands[]`).
 */
const StoneBrandFilter = ({
    brands,
    value,
    restricted = false,
    onChange,
}: {
    /** The brands it offers: only those the Brand answer allows. */
    brands: InquiryBrandOption[];
    value: string[];
    /** Whether the Brand answer chose these brands, so "all" means all of them. */
    restricted?: boolean;
    /** A brand toggled, or null for All brands. */
    onChange: (brand: string | null) => void;
}) => {
    const all = restricted ? 'All chosen brands' : 'All brands';
    const label =
        value.length === 0
            ? brands.length === 1
                ? brands[0].name
                : all
            : value.length === 1
              ? (brands.find(({ id }) => id === value[0])?.name ?? '1 brand')
              : `${value.length} brands`;

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className="w-full justify-between rounded-none bg-background font-normal sm:w-52"
                    aria-label={`Filter by brand: ${label}`}
                    data-testid="stone-brand-filter"
                >
                    <span className="truncate">{label}</span>
                    <ChevronDown aria-hidden className="size-4 opacity-50" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuCheckboxItem
                    checked={value.length === 0}
                    onCheckedChange={() => onChange(null)}
                    onSelect={(event) => event.preventDefault()}
                >
                    {all}
                </DropdownMenuCheckboxItem>
                <DropdownMenuSeparator />
                {brands.map((brand) => (
                    <DropdownMenuCheckboxItem
                        key={brand.id}
                        checked={value.includes(brand.id)}
                        onCheckedChange={() => onChange(brand.id)}
                        onSelect={(event) => event.preventDefault()}
                    >
                        {brand.name}
                    </DropdownMenuCheckboxItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
};

export default StoneBrandFilter;
