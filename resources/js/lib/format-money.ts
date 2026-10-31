/*
 * The one money format the dashboards use. Pure, with no imports, for tests/js.
 */

const PESO_FORMAT = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});

/** "₱1,250.00", from the server's "1250.00" or a number. */
export const formatPeso = (value: string | number): string =>
    PESO_FORMAT.format(Number(value));
