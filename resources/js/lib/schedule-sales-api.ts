import { sales as salesRoute } from '@/routes/schedule';

/**
 * A sales member an appointment may be assigned to, as SalesPickerController
 * sends them. `openAppointmentCount` is how many appointments are still to
 * happen with them (awaiting them or confirmed): informational only.
 */
export type AssignableSales = {
    id: number;
    name: string;
    openAppointmentCount: number;
};

/** The sales members matching a search, by name. Marketing (and the admin) only. */
export const searchAssignableSales = async (
    search: string,
): Promise<{ sales: AssignableSales[] }> => {
    const response = await fetch(
        salesRoute.url({
            query: search.trim() === '' ? {} : { search },
        }),
        {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    );

    if (!response.ok) {
        throw new Error(
            `The sales members could not be loaded (${response.status}).`,
        );
    }

    return (await response.json()) as { sales: AssignableSales[] };
};
