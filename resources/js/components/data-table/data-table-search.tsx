import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';

const SEARCH_DEBOUNCE_MS = 300;

/**
 * A list's search box. It hands the search on 300 ms after the reader stops
 * typing, so a word is one reload rather than one a letter.
 */
const DataTableSearch = ({
    value,
    onSearch,
    placeholder = 'Search',
}: {
    /** The search the page was loaded with. */
    value: string;
    onSearch: (search: string) => void;
    placeholder?: string;
}) => {
    const [search, setSearch] = useState(value);
    const lastSearch = useRef(value);

    useEffect(() => {
        if (search === lastSearch.current) {
            return;
        }

        const timer = window.setTimeout(() => {
            lastSearch.current = search;
            onSearch(search);
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return (
        <div className="relative w-full sm:max-w-xs">
            <Search
                aria-hidden
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-secondary-foreground"
            />
            <Input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder={placeholder}
                aria-label={placeholder}
                className="rounded-none pl-9"
            />
        </div>
    );
};

export default DataTableSearch;
