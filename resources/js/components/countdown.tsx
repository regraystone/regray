import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { secondsUntil } from '@/lib/rate-limit';

/**
 * Renders `children` with the whole seconds left until `until` (a Date.now()
 * timestamp), again each second until it reaches 0. Nothing is left when
 * `until` is null.
 *
 * The clock is read when it mounts, so give it `key={until}`: a new deadline
 * then starts a fresh count rather than one measured from an old clock.
 */
const Countdown = ({
    until,
    children,
}: {
    until: number | null;
    children: (secondsLeft: number) => ReactNode;
}) => {
    const [now, setNow] = useState(() => Date.now());
    const secondsLeft = secondsUntil(until, now);

    useEffect(() => {
        if (secondsLeft <= 0) {
            return;
        }

        const timer = window.setTimeout(() => setNow(Date.now()), 1000);

        return () => window.clearTimeout(timer);
    }, [secondsLeft, now]);

    return <>{children(secondsLeft)}</>;
};

export default Countdown;
