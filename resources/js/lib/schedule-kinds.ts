/*
 * What a client books from the Schedule page (App\Enums\ScheduleKind): a
 * showroom visit, or the installation or the delivery of one of their
 * accepted orders. An inquiry's own target date has no kind.
 */

export type ScheduleKind = 'showroom_visit' | 'installation' | 'delivery';

export const SCHEDULE_KINDS: ScheduleKind[] = [
    'showroom_visit',
    'installation',
    'delivery',
];

/** Same as ScheduleKind::label(). */
export const SCHEDULE_KIND_LABELS: Record<ScheduleKind, string> = {
    showroom_visit: 'Showroom visit',
    installation: 'Installation',
    delivery: 'Delivery',
};

/** An accepted order the client may book for, as ScheduleService::ordersToSchedule() sends it. */
export type SchedulableOrder = {
    reference: string;
    name: string;
    service: string | null;
    /** The kinds it may still be booked for: never a showroom visit. */
    kinds: ScheduleKind[];
};

/** Whether `kind` is booked for one of the client's orders. */
export const isForOrder = (kind: ScheduleKind | null | undefined): boolean =>
    kind === 'installation' || kind === 'delivery';

/** The orders `kind` may be booked for: none for a showroom visit. */
export const ordersFor = (
    orders: SchedulableOrder[],
    kind: ScheduleKind,
): SchedulableOrder[] =>
    isForOrder(kind) ? orders.filter(({ kinds }) => kinds.includes(kind)) : [];

/** "Installation for INQ-0012", or the kind alone without an order. */
export const kindLine = (
    kind: ScheduleKind | null | undefined,
    orderReference: string | null | undefined,
): string | null => {
    if (!kind) {
        return null;
    }

    return orderReference
        ? `${SCHEDULE_KIND_LABELS[kind]} for ${orderReference}`
        : SCHEDULE_KIND_LABELS[kind];
};
