import { useEffect, useState } from 'react';

export type GridPageSize = 10 | 15;

const TABLET_MEDIA_QUERY = '(min-width: 48rem)';

export function useGridPageSize(): GridPageSize | null {
    const [pageSize, setPageSize] = useState<GridPageSize | null>(null);

    useEffect(() => {
        const tablet = window.matchMedia(TABLET_MEDIA_QUERY);
        const update = (): void => setPageSize(tablet.matches ? 15 : 10);

        update();
        tablet.addEventListener('change', update);

        return () => {
            tablet.removeEventListener('change', update);
        };
    }, []);

    return pageSize;
}
