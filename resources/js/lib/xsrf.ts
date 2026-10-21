/** The XSRF token Laravel set as a cookie, for requests made outside Inertia. */
export const readXsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};
