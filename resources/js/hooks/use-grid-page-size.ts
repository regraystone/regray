import { useEffect, useState } from 'react';

export type GridPageSize = 16 | 24;

const TABLET_MEDIA_QUERY = '(min-width: 48rem)';
const COOKIE_NAME = 'grid_page_size';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

/** Whether the browser kept the size, so a reload would actually change the answer. */
export const gridPageSizeCookieIs = (pageSize: GridPageSize): boolean =>
    document.cookie.split('; ').includes(`${COOKIE_NAME}=${String(pageSize)}`);

/**
 * How many products to load at a time on this screen. The server reads it from
 * a cookie, which keeps it out of the URL.
 */
export function useGridPageSize(): GridPageSize | null {
    const [pageSize, setPageSize] = useState<GridPageSize | null>(null);

    useEffect(() => {
        const tablet = window.matchMedia(TABLET_MEDIA_QUERY);
        const update = (): void => {
            const next: GridPageSize = tablet.matches ? 24 : 16;

            document.cookie = `${COOKIE_NAME}=${String(next)}; path=/; max-age=${String(COOKIE_MAX_AGE)}; samesite=lax`;
            setPageSize(next);
        };

        update();
        tablet.addEventListener('change', update);

        return () => {
            tablet.removeEventListener('change', update);
        };
    }, []);

    return pageSize;
}
