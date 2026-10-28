/*
 * The one date format the dashboards use. Pure, with no imports, for tests/js.
 *
 * Always read in the studio's zone, whatever zone the browser is in, so a date
 * near midnight is the same day for everyone.
 */

const DATE_FORMAT = new Intl.DateTimeFormat('en-US', {
    dateStyle: 'long',
    timeZone: 'Asia/Manila',
});

/** "June 22, 2020", from an ISO string or a Date. */
export const formatDate = (value: string | Date): string =>
    DATE_FORMAT.format(typeof value === 'string' ? new Date(value) : value);
