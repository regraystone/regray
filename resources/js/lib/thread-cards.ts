/*
 * The words on the inquiry and schedule cards in a thread. Pure, with no
 * imports, for tests/js.
 */

export type CardInquiryStatus =
    | 'draft'
    | 'pending'
    | 'quoted'
    | 'paid'
    | 'completed'
    | 'cancelled';

export type CardScheduleStatus =
    | 'requested'
    | 'confirmed'
    | 'completed'
    | 'cancelled';

export type CardBookedByType = 'client' | 'staff';

/** The headline an inquiry card announces, from the status it was posted for. */
export const INQUIRY_HEADLINES: Record<CardInquiryStatus, string> = {
    draft: 'Project drafted',
    pending: 'Project submitted',
    quoted: 'Project quoted',
    paid: 'Project paid',
    completed: 'Project completed',
    cancelled: 'Project cancelled',
};

export const inquiryHeadline = (status: CardInquiryStatus): string =>
    INQUIRY_HEADLINES[status];

const plural = (count: number, one: string, many: string): string =>
    `${count} ${count === 1 ? one : many}`;

/** "2 projects · Inquiry No: INQ-1048" */
export const inquirySubline = (
    projectCount: number,
    reference: string,
): string =>
    `${plural(projectCount, 'project', 'projects')} · Inquiry No: ${reference}`;

/** "1 service", "3 services", "0 services" */
export const serviceCountText = (count: number): string =>
    plural(count, 'service', 'services');

const SCHEDULE_HEADLINES: Record<
    CardScheduleStatus,
    Record<CardBookedByType, string>
> = {
    requested: { client: 'Booking requested', staff: 'Booking requested' },
    confirmed: { client: 'Booking approved', staff: 'Visit booked' },
    completed: { client: 'Visit completed', staff: 'Visit completed' },
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
