import { useSyncExternalStore } from 'react';
import type { ReactNode } from 'react';
import type { StaggeredMode } from '@/lib/staggered-layout';
import {
    STAGGERED_COLUMN_COUNT,
    withStaggeredAspects,
} from '@/lib/staggered-layout';

const DESKTOP_MEDIA_QUERY = '(min-width: 1024px)';

function useColumnsMode(): StaggeredMode {
    const isDesktop = useSyncExternalStore(
        (onChange) => {
            const media = window.matchMedia(DESKTOP_MEDIA_QUERY);

            media.addEventListener('change', onChange);

            return () => media.removeEventListener('change', onChange);
        },
        () => window.matchMedia(DESKTOP_MEDIA_QUERY).matches,
        () => false,
    );

    return isDesktop ? 'three-column' : 'two-column';
}

type StaggeredGridProps<T extends { id: string }> = {
    items: T[];
    renderItem: (item: T, aspect: string) => ReactNode;
    emptyState?: ReactNode;
};

function StaggeredGrid<T extends { id: string }>({
    items,
    renderItem,
    emptyState,
}: StaggeredGridProps<T>) {
    const mode = useColumnsMode();

    if (items.length === 0 && emptyState) {
        return <>{emptyState}</>;
    }

    const columns = STAGGERED_COLUMN_COUNT[mode];

    return (
        <div className="feed-masonry">
            {withStaggeredAspects(items, columns).map(
                (columnItems, columnIndex) => (
                    <div key={columnIndex} className="feed-column">
                        {columnItems.map((item) => (
                            <div key={item.id}>
                                {renderItem(item, item.aspect)}
                            </div>
                        ))}
                    </div>
                ),
            )}
        </div>
    );
}

export default StaggeredGrid;
