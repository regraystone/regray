import { useEffect, useState } from 'react';

/**
 * Five rows of the card grid (`grid-cols-2 md:grid-cols-4 xl:grid-cols-5`) at
 * each Tailwind breakpoint, so a loaded page always ends on a full row. The
 * values are the server's App\Enums\HomeGridPageSize.
 */
export type GridPageSize = 10 | 20 | 25;

const LARGE_MEDIA_QUERY = '(min-width: 80rem)';
const TABLET_MEDIA_QUERY = '(min-width: 48rem)';

/**
 * How many cards a page of the grid should load on this screen. `null` until
 * the viewport has been measured, so the server render and first client render
 * match and nothing is fetched for a size that was only guessed.
 */
export function useGridPageSize(): GridPageSize | null {
    const [pageSize, setPageSize] = useState<GridPageSize | null>(null);

    useEffect(() => {
        const large = window.matchMedia(LARGE_MEDIA_QUERY);
        const tablet = window.matchMedia(TABLET_MEDIA_QUERY);
        const update = (): void =>
            setPageSize(large.matches ? 25 : tablet.matches ? 20 : 10);

        update();
        large.addEventListener('change', update);
        tablet.addEventListener('change', update);

        return () => {
            large.removeEventListener('change', update);
            tablet.removeEventListener('change', update);
        };
    }, []);

    return pageSize;
}
