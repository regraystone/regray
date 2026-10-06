import { useEffect, useState } from 'react';

/**
 * Sonner switches to its mobile layout at 600px, so this uses the same value
 * rather than Tailwind's 640px `sm`. Otherwise, between 601 and 639px the
 * position would say "mobile" while Sonner was still laying out for desktop.
 */
const MOBILE_MEDIA_QUERY = '(max-width: 600px)';

/**
 * Whether the viewport is in Sonner's mobile range. Starts as `false` so the
 * server render and first client render match.
 */
export function useIsMobile(): boolean {
    const [isMobile, setIsMobile] = useState(false);

    useEffect(() => {
        const mediaQuery = window.matchMedia(MOBILE_MEDIA_QUERY);
        const update = (): void => setIsMobile(mediaQuery.matches);

        update();
        mediaQuery.addEventListener('change', update);

        return () => mediaQuery.removeEventListener('change', update);
    }, []);

    return isMobile;
}
