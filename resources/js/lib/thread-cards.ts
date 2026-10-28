/*
 * The words on the inquiry and schedule cards in a thread. Pure, with no
 * imports, for tests/js.
 */

/** The display statuses an inquiry card can announce. */
export type CardInquiryStatus =
    | 'pending'
    | 'cancelled'
    | 'closed'
    | 'negotiation'
    | 'site_visited'
    | 'on_hold'
    | 'quoted'
    | 'order_received'
    | 'measure'
    | 'sourcing'
    | 'production'
    | 'quality_check'
    | 'ready'
    | 'dispatched'
    | 'installation'
    | 'delayed';

export type CardScheduleStatus =
    | 'pending'
    | 'confirmed'
    | 'completed'
    | 'cancelled';

export type CardBookedByType = 'client' | 'staff';

/**
 * The headline an inquiry card announces, from the display status it was
 * posted for. The payment status it was posted for is the card's second badge.
 */
export const INQUIRY_HEADLINES: Record<CardInquiryStatus, string> = {
    // A pending card is the one a newly sent inquiry posts.
    pending: 'New Inquiry',
    cancelled: 'Inquiry cancelled',
    closed: 'Inquiry closed',
    negotiation: 'In negotiation',
    site_visited: 'Site visited',
    on_hold: 'Inquiry on hold',
    quoted: 'Quotation approved',
    order_received: 'Job order received',
    measure: 'Measuring',
    sourcing: 'Sourcing stone',
    production: 'In production',
    quality_check: 'Quality check',
    ready: 'Ready to dispatch',
    dispatched: 'Dispatched',
    installation: 'Installing',
    delayed: 'Inquiry delayed',
};

export const inquiryHeadline = (status: CardInquiryStatus): string =>
    INQUIRY_HEADLINES[status];

const SCHEDULE_HEADLINES: Record<
    CardScheduleStatus,
    Record<CardBookedByType, string>
> = {
    pending: { client: 'Booking pending', staff: 'Booking pending' },
    confirmed: { client: 'Booking approved', staff: 'Appointment booked' },
    completed: {
        client: 'Appointment completed',
        staff: 'Appointment completed',
    },
    cancelled: { client: 'Booking cancelled', staff: 'Booking cancelled' },
};

/**
 * A visit card's headline. Only "confirmed" depends on who booked it: a
 * client's booking was approved by the team, a staff booking was simply made.
 */
export const scheduleHeadline = (
    status: CardScheduleStatus,
    bookedByType: CardBookedByType,
): string => SCHEDULE_HEADLINES[status][bookedByType];

/** "Booked by client", or the staff member's name when staff booked it. */
export const bookedByText = (
    bookedByType: CardBookedByType,
    bookedByName: string | null,
): string =>
    bookedByType === 'staff'
        ? `Booked by ${bookedByName ?? 'staff'}`
        : 'Booked by client';
