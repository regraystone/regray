import type { ReactNode } from 'react';

/**
 * Above a list: the search on the left and the filters on the right, stacked
 * on a phone.
 */
const DataTableToolbar = ({
    search,
    filters,
}: {
    search: ReactNode;
    filters?: ReactNode;
}) => (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        {search}
        {filters ? (
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                {filters}
            </div>
        ) : null}
    </div>
);

export default DataTableToolbar;
